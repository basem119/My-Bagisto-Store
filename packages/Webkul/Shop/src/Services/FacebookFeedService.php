<?php

namespace Webkul\Shop\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use XMLWriter;
use Webkul\Product\Models\ProductFlatProxy;

class FacebookFeedService
{
    public function buildFeedXml(): string
    {
        $products = $this->getFeedProducts();

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $writer->startElement('channel');
        $writer->writeElement('title', core()->getCurrentChannel()->name ?? 'Product Feed');
        $writer->writeElement('link', $this->forceHttps(url('/')));
        $writer->writeElement('description', 'Facebook Commerce product feed generated from Bagisto.');

        foreach ($products as $productFlat) {
            $this->writeProductItem($writer, $productFlat);
        }

        $writer->endElement();
        $writer->endElement();

        return $writer->outputMemory();
    }

    protected function getFeedProducts()
    {
        $channel = core()->getRequestedChannelCode();
        $locale = app()->getLocale();

        return ProductFlatProxy::modelClass()::with(['product.images', 'product.categories'])
            ->where('status', 1)
            ->where('visible_individually', 1)
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->whereNotNull('url_key')
            ->whereHas('product.images')
            ->orderBy('product_id')
            ->get();
    }

    protected function writeProductItem(XMLWriter $writer, $productFlat): void
    {
        $link = $this->forceHttps(route('shop.product_or_category.index', $productFlat->url_key, true));
        $imageLink = $this->getImageLink($productFlat);

        if (! $imageLink || ! $productFlat->name) {
            return;
        }

        $writer->startElement('item');
        $writer->writeElement('g:id', (string) ($productFlat->sku ?: $productFlat->product_id));
        $writer->writeElement('g:title', $this->cleanText($productFlat->name));
        $writer->writeElement('g:description', $this->cleanText($productFlat->short_description ?? $productFlat->description ?? ''));
        $writer->writeElement('g:link', $link);
        $writer->writeElement('g:image_link', $imageLink);
        $writer->writeElement('g:availability', $this->getAvailability($productFlat));
        $writer->writeElement('g:condition', 'new');
        $writer->writeElement('g:price', $this->getPriceValue($productFlat));

        if ($brand = $this->getBrand($productFlat)) {
            $writer->writeElement('g:brand', $this->cleanText($brand));
        }

        if ($productType = $this->getProductType($productFlat)) {
            $writer->writeElement('g:product_type', $this->cleanText($productType));
        }

        $writer->endElement();
    }

    protected function getImageLink($productFlat): ?string
    {
        $image = $productFlat->product->images->first();

        if (! $image?->url) {
            return null;
        }

        return $this->forceHttps($image->url);
    }

    protected function getAvailability($productFlat): string
    {
        try {
            return $productFlat->product->isSaleable() ? 'in stock' : 'out of stock';
        } catch (\Throwable $exception) {
            return 'in stock';
        }
    }

    protected function getPriceValue($productFlat): string
    {
        $price = $productFlat->special_price && $this->isSpecialPriceActive($productFlat)
            ? $productFlat->special_price
            : $productFlat->price;

        return number_format($price, 2, '.', '') . ' ' . core()->getCurrentCurrencyCode();
    }

    protected function isSpecialPriceActive($productFlat): bool
    {
        if (! $productFlat->special_price) {
            return false;
        }

        $today = now()->startOfDay();
        $from = $productFlat->special_price_from ? now()->parse($productFlat->special_price_from)->startOfDay() : null;
        $to = $productFlat->special_price_to ? now()->parse($productFlat->special_price_to)->endOfDay() : null;

        if ($from && $today->lt($from)) {
            return false;
        }

        if ($to && $today->gt($to)) {
            return false;
        }

        return true;
    }

    protected function getBrand($productFlat): ?string
    {
        return $productFlat->brand ?? data_get(core()->getCurrentChannel(), 'name');
    }

    protected function getProductType($productFlat): ?string
    {
        $categories = $productFlat->product->categories->pluck('name')->filter()->all();

        return $categories ? implode(' > ', $categories) : null;
    }

    protected function cleanText(string $value): string
    {
        $value = strip_tags($value);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', ' ', trim($value));
    }

    protected function forceHttps(string $url): string
    {
        if (Str::startsWith($url, '//')) {
            return 'https:' . $url;
        }

        return preg_replace('/^http:/i', 'https:', $url);
    }
}

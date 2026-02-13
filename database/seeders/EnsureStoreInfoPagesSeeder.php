<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnsureStoreInfoPagesSeeder extends Seeder
{
    /**
     * Ensure storefront info pages and footer links exist.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $defaultLocale = config('app.locale', 'en');
        $locales = DB::table('locales')->pluck('code')->all();

        if (empty($locales)) {
            $locales = [$defaultLocale];
        }

        $channelIds = DB::table('channels')->pluck('id')->all();

        if (empty($channelIds)) {
            $channelIds = [1];
        }

        $pages = [
            'about-us' => [
                'title' => 'About Us',
                'meta'  => 'about us',
            ],
            'customer-service' => [
                'title' => 'Customer Service',
                'meta'  => 'customer service',
            ],
            'whats-new' => [
                'title' => 'What\'s New',
                'meta'  => 'whats new',
            ],
            'terms-of-use' => [
                'title' => 'Terms of Use',
                'meta'  => 'terms of use',
            ],
            'terms-conditions' => [
                'title' => 'Terms & Conditions',
                'meta'  => 'terms and conditions',
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'meta'  => 'privacy policy',
            ],
            'payment-policy' => [
                'title' => 'Payment Policy',
                'meta'  => 'payment policy',
            ],
            'shipping-policy' => [
                'title' => 'Shipping Policy',
                'meta'  => 'shipping policy',
            ],
            'refund-policy' => [
                'title' => 'Refund Policy',
                'meta'  => 'refund policy',
            ],
            'return-policy' => [
                'title' => 'Return Policy',
                'meta'  => 'return policy',
            ],
        ];

        foreach ($pages as $slug => $content) {
            $cmsPageId = DB::table('cms_page_translations')
                ->where('url_key', $slug)
                ->value('cms_page_id');

            if (! $cmsPageId) {
                $cmsPageId = DB::table('cms_pages')->insertGetId([
                    'layout'     => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($locales as $locale) {
                $existingTranslationId = DB::table('cms_page_translations')
                    ->where('cms_page_id', $cmsPageId)
                    ->where('locale', $locale)
                    ->where('url_key', $slug)
                    ->value('id');

                $html = $this->getDefaultHtmlContent($slug);

                if (! $existingTranslationId) {
                    DB::table('cms_page_translations')->insert([
                        'cms_page_id'      => $cmsPageId,
                        'locale'           => $locale,
                        'url_key'          => $slug,
                        'page_title'       => $content['title'],
                        'html_content'     => $html,
                        'meta_title'       => $content['title'],
                        'meta_description' => $content['title'],
                        'meta_keywords'    => $content['meta'],
                    ]);

                    continue;
                }

                if ($locale !== $defaultLocale) {
                    continue;
                }

                DB::table('cms_page_translations')
                    ->where('id', $existingTranslationId)
                    ->update([
                        'page_title'       => $content['title'],
                        'html_content'     => $html,
                        'meta_title'       => $content['title'],
                        'meta_description' => $content['title'],
                        'meta_keywords'    => $content['meta'],
                    ]);
            }

            foreach ($channelIds as $channelId) {
                $channelLinked = DB::table('cms_page_channels')
                    ->where('cms_page_id', $cmsPageId)
                    ->where('channel_id', $channelId)
                    ->exists();

                if ($channelLinked) {
                    continue;
                }

                DB::table('cms_page_channels')->insert([
                    'cms_page_id' => $cmsPageId,
                    'channel_id'  => $channelId,
                ]);
            }
        }

        $requiredLinks = [
            [
                'slug'       => 'about-us',
                'title'      => 'About Us',
                'path'       => '/page/about-us',
                'sort_order' => 1,
                'column'     => 'column_1',
            ],
            [
                'slug'       => 'contact-us',
                'title'      => 'Contact Us',
                'path'       => '/contact-us',
                'sort_order' => 2,
                'column'     => 'column_1',
            ],
            [
                'slug'       => 'customer-service',
                'title'      => 'Customer Service',
                'path'       => '/page/customer-service',
                'sort_order' => 3,
                'column'     => 'column_1',
            ],
            [
                'slug'       => 'whats-new',
                'title'      => 'What\'s New',
                'path'       => '/page/whats-new',
                'sort_order' => 4,
                'column'     => 'column_1',
            ],
            [
                'slug'       => 'terms-of-use',
                'title'      => 'Terms of Use',
                'path'       => '/page/terms-of-use',
                'sort_order' => 5,
                'column'     => 'column_1',
            ],
            [
                'slug'       => 'terms-conditions',
                'title'      => 'Terms & Conditions',
                'path'       => '/page/terms-conditions',
                'sort_order' => 6,
                'column'     => 'column_1',
            ],
            [
                'slug'       => 'privacy-policy',
                'title'      => 'Privacy Policy',
                'path'       => '/page/privacy-policy',
                'sort_order' => 1,
                'column'     => 'column_2',
            ],
            [
                'slug'       => 'payment-policy',
                'title'      => 'Payment Policy',
                'path'       => '/page/payment-policy',
                'sort_order' => 2,
                'column'     => 'column_2',
            ],
            [
                'slug'       => 'shipping-policy',
                'title'      => 'Shipping Policy',
                'path'       => '/page/shipping-policy',
                'sort_order' => 3,
                'column'     => 'column_2',
            ],
            [
                'slug'       => 'refund-policy',
                'title'      => 'Refund Policy',
                'path'       => '/page/refund-policy',
                'sort_order' => 4,
                'column'     => 'column_2',
            ],
            [
                'slug'       => 'return-policy',
                'title'      => 'Return Policy',
                'path'       => '/page/return-policy',
                'sort_order' => 5,
                'column'     => 'column_2',
            ],
        ];

        $footerCustomizations = DB::table('theme_customizations')
            ->where('type', 'footer_links')
            ->pluck('id')
            ->all();

        foreach ($footerCustomizations as $footerCustomizationId) {
            $translations = DB::table('theme_customization_translations')
                ->where('theme_customization_id', $footerCustomizationId)
                ->get(['id', 'options']);

            foreach ($translations as $translation) {
                $options = json_decode($translation->options, true);

                if (! is_array($options)) {
                    $options = [];
                }

                if (! isset($options['column_1']) || ! is_array($options['column_1'])) {
                    $options['column_1'] = [];
                }

                if (! isset($options['column_2']) || ! is_array($options['column_2'])) {
                    $options['column_2'] = [];
                }

                $existingPaths = [];

                foreach (['column_1', 'column_2'] as $column) {
                    $normalizedLinks = [];
                    $seenPaths = [];

                    foreach ($options[$column] as $link) {
                        if (empty($link['url'])) {
                            continue;
                        }

                        $path = $this->normalizePath($link['url']);

                        if (isset($seenPaths[$path])) {
                            continue;
                        }

                        $seenPaths[$path] = true;
                        $existingPaths[$path] = true;
                        $link['url'] = $path;
                        $normalizedLinks[] = $link;
                    }

                    $options[$column] = $normalizedLinks;
                }

                foreach ($requiredLinks as $link) {
                    if (isset($existingPaths[$link['path']])) {
                        continue;
                    }

                    $options[$link['column']][] = [
                        'url'        => $link['path'],
                        'title'      => $link['title'],
                        'sort_order' => $link['sort_order'],
                    ];
                }

                DB::table('theme_customization_translations')
                    ->where('id', $translation->id)
                    ->update([
                        'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                    ]);
            }
        }
    }

    /**
     * Normalize absolute or relative URLs to path only.
     */
    private function normalizePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return '/'.ltrim($path, '/');
    }

    /**
     * Return default, standard HTML content for CMS pages.
     */
    private function getDefaultHtmlContent(string $slug): string
    {
        return match ($slug) {
            'about-us' => '<div class="static-container"><h2 class="mb-4 text-3xl">Our Story</h2><p class="mb-4">We built our store to offer products that combine quality, practical use, and clean design. Every collection is selected to help customers find reliable essentials for daily life and travel.</p><p class="mb-4">Our team focuses on consistent quality checks, clear communication, and dependable after-sales support. We are committed to improving every step of the shopping journey, from product discovery to delivery.</p><h3 class="mb-3 mt-6 text-2xl">What Makes Us Different</h3><ul class="list-disc pl-6"><li>Carefully selected products with quality standards.</li><li>Transparent policies for shipping, returns, and refunds.</li><li>Customer-first support before and after purchase.</li><li>Continuous improvement based on customer feedback.</li></ul></div>',
            'customer-service' => '<div class="static-container"><h2 class="mb-4 text-3xl">Customer Service</h2><p class="mb-4">Our customer service team is available to help with order tracking, product questions, payment concerns, and account support.</p><h3 class="mb-3 mt-6 text-2xl">How We Support You</h3><ul class="list-disc pl-6"><li>Order updates and delivery status assistance.</li><li>Guidance on returns, exchanges, and refunds.</li><li>Help with account and checkout issues.</li><li>Product recommendations based on your needs.</li></ul><p class="mt-4">For direct help, please use the Contact Us page and include your order number when available.</p></div>',
            'whats-new' => '<div class="static-container"><h2 class="mb-4 text-3xl">What&apos;s New</h2><p class="mb-4">Stay updated with our latest product drops, seasonal highlights, and service improvements.</p><h3 class="mb-3 mt-6 text-2xl">On This Page You Will Find</h3><ul class="list-disc pl-6"><li>New arrivals and popular restocks.</li><li>Limited-time collections and promotions.</li><li>Updates to shipping, payment, and service experience.</li><li>Announcements that help you shop with confidence.</li></ul></div>',
            'terms-of-use' => '<div class="static-container"><h2 class="mb-4 text-3xl">Terms of Use</h2><p class="mb-4">By accessing this website, you agree to use it lawfully and in accordance with these terms.</p><h3 class="mb-3 mt-6 text-2xl">Website Use</h3><ul class="list-disc pl-6"><li>Use the site for personal and legitimate shopping purposes only.</li><li>Do not attempt unauthorized access to systems or customer data.</li><li>Do not misuse content, trademarks, or proprietary assets.</li><li>We may update content, pricing, and availability without prior notice.</li></ul><p class="mt-4">If you do not agree with these terms, please discontinue use of the website.</p></div>',
            'terms-conditions' => '<div class="static-container"><h2 class="mb-4 text-3xl">Terms &amp; Conditions</h2><p class="mb-4">These terms govern purchases made through our store and describe rights and responsibilities for both customers and the business.</p><h3 class="mb-3 mt-6 text-2xl">Orders and Payments</h3><ul class="list-disc pl-6"><li>All orders are subject to stock availability and payment confirmation.</li><li>We reserve the right to cancel or limit orders when necessary.</li><li>Product details and pricing are maintained with care, but errors may occur and can be corrected.</li></ul><h3 class="mb-3 mt-6 text-2xl">Liability</h3><p>We are not responsible for indirect damages resulting from website use, delays, or service interruptions beyond reasonable control.</p></div>',
            'privacy-policy' => '<div class="static-container"><h2 class="mb-4 text-3xl">Privacy Policy</h2><p class="mb-4">Your privacy matters to us. We collect only the information needed to process orders, provide support, and improve your shopping experience.</p><h3 class="mb-3 mt-6 text-2xl">Information We Collect</h3><ul class="list-disc pl-6"><li>Contact and shipping details for order fulfillment.</li><li>Payment-related metadata required for transaction processing.</li><li>Usage data to improve performance and user experience.</li></ul><h3 class="mb-3 mt-6 text-2xl">How We Use Data</h3><ul class="list-disc pl-6"><li>To complete orders and provide customer support.</li><li>To send essential service and order notifications.</li><li>To maintain security, prevent fraud, and comply with legal obligations.</li></ul></div>',
            'payment-policy' => '<div class="static-container"><h2 class="mb-4 text-3xl">Payment Policy</h2><p class="mb-4">We support secure payment processing through approved payment methods displayed at checkout.</p><h3 class="mb-3 mt-6 text-2xl">Payment Rules</h3><ul class="list-disc pl-6"><li>Orders are confirmed only after successful payment authorization.</li><li>Failed or declined transactions may require an alternative payment method.</li><li>Prices include applicable taxes when indicated at checkout.</li><li>In case of payment verification issues, order processing may be delayed.</li></ul></div>',
            'shipping-policy' => '<div class="static-container"><h2 class="mb-4 text-3xl">Shipping Policy</h2><p class="mb-4">We process orders as quickly as possible and share shipping updates once your package is prepared.</p><h3 class="mb-3 mt-6 text-2xl">Shipping Details</h3><ul class="list-disc pl-6"><li>Delivery timelines depend on destination and courier availability.</li><li>Shipping fees, if any, are shown during checkout before payment.</li><li>Customers are responsible for providing accurate delivery information.</li><li>Delays caused by external carriers or force majeure may occur.</li></ul></div>',
            'refund-policy' => '<div class="static-container"><h2 class="mb-4 text-3xl">Refund Policy</h2><p class="mb-4">If your return is approved, refunds are issued to the original payment method according to provider processing times.</p><h3 class="mb-3 mt-6 text-2xl">Refund Conditions</h3><ul class="list-disc pl-6"><li>Refund eligibility depends on product condition and policy compliance.</li><li>Shipping charges may be non-refundable unless otherwise stated.</li><li>Refund timelines vary by payment provider and bank processing cycles.</li><li>We may reject refunds for items that do not meet return criteria.</li></ul></div>',
            'return-policy' => '<div class="static-container"><h2 class="mb-4 text-3xl">Return Policy</h2><p class="mb-4">We accept returns for eligible items within the stated return window, provided the product is unused and in its original condition.</p><h3 class="mb-3 mt-6 text-2xl">Return Requirements</h3><ul class="list-disc pl-6"><li>Item must be returned with original packaging and accessories.</li><li>Proof of purchase or order number is required.</li><li>Products showing damage from misuse may not be accepted.</li><li>Some product categories may be non-returnable for hygiene or safety reasons.</li></ul><p class="mt-4">Please contact customer service before shipping any return so we can provide the correct instructions.</p></div>',
            default => '<div class="static-container"><p>Content will be updated soon.</p></div>',
        };
    }
}

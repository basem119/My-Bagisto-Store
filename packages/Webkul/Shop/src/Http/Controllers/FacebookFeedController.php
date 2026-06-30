<?php

namespace Webkul\Shop\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Webkul\Shop\Services\FacebookFeedService;

class FacebookFeedController extends Controller
{
    public function __construct(protected FacebookFeedService $feedService)
    {
    }

    public function index(): Response
    {
        $cacheKey = sprintf('facebook_feed_xml_%s_%s', core()->getRequestedChannelCode(), app()->getLocale());

        $content = Cache::remember($cacheKey, now()->addMinutes(30), function () {
            return $this->feedService->buildFeedXml();
        });

        return response($content, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}

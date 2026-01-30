<?php

namespace Webkul\Paymob\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;
use Webkul\Paymob\Listeners\RefundPaymob;

class PaymobServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Payment methods config
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/payment-methods.php',
            'payment_methods'
        );

        // System config
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/system.php',
            'core'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Web routes
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/web.php');

        // Views & translations
        $this->loadViewsFrom(
            __DIR__ . '/../Resources/views',
            'paymob'
        );

        $this->loadTranslationsFrom(
            __DIR__ . '/../Resources/lang',
            'paymob'
        );

        /**
         * ✅ Publish files (console only)
         */
        if ($this->app->runningInConsole()) {

            /**
             * 🔹 Publish payment gateway assets (logo, images)
             * from:
             * packages/Webkul/Paymob/Resources/assets
             * to:
             * public/vendor/paymob
             */
            $this->publishes([
                __DIR__ . '/../Resources/assets' => public_path('vendor/paymob'),
            ], 'paymob-assets');

            /**
             * 🔹 Publish Admin Order View override
             * from:
             * packages/Webkul/Paymob/Resources/views/admin
             * to:
             * resources/views/vendor/admin
             */
            $this->publishes([
                __DIR__ . '/../Resources/views/admin' =>
                    resource_path('views/vendor/admin'),
            ], 'paymob-admin-views');
        }

        /**
         * ✅ Bind refund event to Paymob
         */
        Event::listen(
            'sales.refund.save.after',
            RefundPaymob::class
        );
    }
}

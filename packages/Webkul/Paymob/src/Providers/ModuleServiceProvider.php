<?php

namespace Webkul\Paymob\Providers;

use Webkul\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [];

    /**
     * Register services.
     */
    public function register(): void
    {
        parent::register();

        $this->app->register(PaymobServiceProvider::class);
    }
}
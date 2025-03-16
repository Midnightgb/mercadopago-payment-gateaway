<?php

namespace Midnight\MercadoPago\Providers;

use Webkul\Core\Providers\CoreModuleServiceProvider;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    protected $models = [];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(MercadoPagoServiceProvider::class);
    }
}

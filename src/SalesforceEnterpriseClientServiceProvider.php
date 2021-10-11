<?php
namespace Codedefective\SalesforceEnterpriseClient;

use Illuminate\Support\ServiceProvider;

class SalesforceEnterpriseClientServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/sf_config.php' => config_path('salesforce_enterprise.php'),
        ], 'config');
    }
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sf_config.php', 'salesforce_enterprise');
    }
}

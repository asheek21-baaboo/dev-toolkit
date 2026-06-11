<?php

namespace MyVendor\DevToolkit;

use Illuminate\Support\ServiceProvider;

class ToolkitServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\InstallToolsCommand::class,
            ]);
        }
    }
}
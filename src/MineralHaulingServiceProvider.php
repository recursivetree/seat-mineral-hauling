<?php

namespace RecursiveTree\Seat\MineralHauling;

use Seat\Services\AbstractSeatPlugin;

class MineralHaulingServiceProvider extends AbstractSeatPlugin
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'mineralhauling');
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'mineralhauling');
        $this->publishes( [__DIR__ . '/resources/js'  => public_path('mineralhauling/js')]);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/package.sidebar.tools.php', 'package.sidebar.tools.entries');
    }

    /**
     * Return the plugin public name as it should be displayed into settings.
     *
     * @return string
     * @example SeAT Web
     *
     */
    public function getName(): string
    {
        return 'Mineral Hauling Calculator';
    }

    /**
     * Return the plugin repository address.
     *
     * @example https://github.com/eveseat/web
     *
     * @return string
     */
    public function getPackageRepositoryUrl(): string
    {
        return 'todo';
    }

    /**
     * Return the plugin technical name as published on package manager.
     *
     * @return string
     * @example web
     *
     */
    public function getPackagistPackageName(): string
    {
        return 'seat-mineral-hauling';
    }

    /**
     * Return the plugin vendor tag as published on package manager.
     *
     * @return string
     * @example eveseat
     *
     */
    public function getPackagistVendorName(): string
    {
        return 'recursivetree';
    }
}
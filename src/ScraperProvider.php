<?php

namespace Joskfg\LaravelIntelligentScraper;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Joskfg\LaravelIntelligentScraper\Scraper\Application\XpathBuilder;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ConfigurationScraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Joskfg\LaravelIntelligentScraper\Scraper\Listeners\ConfigureScraper;
use Joskfg\LaravelIntelligentScraper\Scraper\Listeners\Scrape;
use Joskfg\LaravelIntelligentScraper\Scraper\Listeners\ScrapedListener;
use Joskfg\LaravelIntelligentScraper\Scraper\Listeners\ScrapeFailedListener;
use Joskfg\LaravelIntelligentScraper\Scraper\Listeners\UpdateDataset;

class ScraperProvider extends EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        ScrapeRequest::class        => [
            Scrape::class,
        ],
        InvalidConfiguration::class => [
            ConfigureScraper::class,
        ],
        Scraped::class              => [
            UpdateDataset::class,
            ScrapedListener::class,
        ],
        ConfigurationScraped::class => [
            UpdateDataset::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [];

    public function boot(): void
    {
        parent::boot();

        $this->publishes(
            [__DIR__ . '/config/scraper.php' => config_path('scraper.php')],
            'config'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/config/scraper.php',
            'scraper'
        );

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * Register any application services.
     *
     */
    public function register(): void
    {
        parent::register();

        $this->app->when(XpathBuilder::class)
            ->needs('$idsToIgnore')
            ->give(fn () => config('scraper.xpath.ignore-identifiers'));

        $this->app->when(ScrapedListener::class)
            ->needs('$listeners')
            ->give(fn () => config('scraper.listeners.scraped'));

        $this->app->when(ScrapeFailedListener::class)
            ->needs('$listeners')
            ->give(fn () => config('scraper.listeners.scrape-failed'));
    }
}

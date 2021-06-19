<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Listeners;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Softonic\LaravelIntelligentScraper\Scraper\Events\Scraped;

class ScrapedListener implements ShouldQueue
{
    private array $listeners;

    public function __construct(array $listeners)
    {
        $this->listeners = $listeners;
    }

    /**
     * @throws Exception
     */
    public function handle(Scraped $scraped): void
    {
        if (isset($this->listeners[$scraped->scrapeRequest->type])) {
            resolve($this->listeners[$scraped->scrapeRequest->type])->handle($scraped);
        }
    }
}

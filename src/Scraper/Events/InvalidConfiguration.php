<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Events;

class InvalidConfiguration
{
    public ScrapeRequest $scrapeRequest;

    public function __construct(ScrapeRequest $scrapeRequest)
    {
        $this->scrapeRequest = $scrapeRequest;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * Only if you are using Horizon
     *
     * @see https://laravel.com/docs/5.8/horizon#tags
     */
    public function tags(): array
    {
        return [
            "reconfigure_type:{$this->scrapeRequest->type}",
        ];
    }
}

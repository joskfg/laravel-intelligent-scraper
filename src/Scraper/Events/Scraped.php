<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;

class Scraped
{
    use Dispatchable;
    use SerializesModels;

    public ScrapeRequest $scrapeRequest;

    public ScrapedData $scrapedData;

    /**
     * Create a new event instance.
     */
    public function __construct(ScrapeRequest $scrapeRequest, ScrapedData $scrapedData)
    {
        $this->scrapeRequest = $scrapeRequest;
        $this->scrapedData = $scrapedData;
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
            "scraped_type:{$this->scrapeRequest->type}",
            "scraped_variant:{$this->scrapedData->getVariant()}",
        ];
    }
}

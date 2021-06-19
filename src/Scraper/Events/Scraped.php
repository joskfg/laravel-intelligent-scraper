<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Scraped
{
    use Dispatchable;
    use SerializesModels;

    public ScrapeRequest $scrapeRequest;

    public array $data;

    public string $variant;

    /**
     * Create a new event instance.
     */
    public function __construct(ScrapeRequest $scrapeRequest, array $data, string $variant)
    {
        $this->scrapeRequest = $scrapeRequest;
        $this->data          = $data;
        $this->variant       = $variant;
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
            "scraped_variant:$this->variant",
        ];
    }
}

<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScrapeFailed
{
    use Dispatchable;
    use SerializesModels;

    public ScrapeRequest $scrapeRequest;

    /**
     * Create a new event instance.
     */
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
            "failed_type:{$this->scrapeRequest->type}",
        ];
    }
}

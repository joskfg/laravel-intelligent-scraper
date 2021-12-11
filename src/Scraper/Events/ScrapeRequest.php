<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScrapeRequest
{
    use Dispatchable;
    use SerializesModels;

    public string $url;

    public string $type;

    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(string $url, string $type, array $context = [])
    {
        $this->url     = $url;
        $this->type    = $type;
        $this->context = $context;
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
        return ["request_type:$this->type"];
    }
}

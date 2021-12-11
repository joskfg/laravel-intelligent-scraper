<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Listeners;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Softonic\LaravelIntelligentScraper\Scraper\Entities\Field;
use Softonic\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;

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
        $this->requestAutomaticNestedScrapes($scraped);
        $this->fireUserListeners($scraped);
    }

    protected function requestAutomaticNestedScrapes(Scraped $scraped): void
    {
        $fields = $scraped->scrapedData->getFields();
        $fields = array_filter(
            $fields,
            static fn (Field $field) => $field->isFound() && $field->getChainType() !== null
        );

        foreach ($fields as $field) {
            event(new ScrapeRequest($field->getValue(), $field->getChainType()));
        }
    }

    protected function fireUserListeners(Scraped $scraped): void
    {
        if (isset($this->listeners[$scraped->scrapeRequest->type])) {
            resolve($this->listeners[$scraped->scrapeRequest->type])->handle($scraped);
        }
    }
}

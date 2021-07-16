<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Support\Facades\App;
use Softonic\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Softonic\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Tests\TestCase;

class ScrapedListenerTest extends TestCase
{
    /**
     * @test
     */
    public function whenReceiveAnUnknownScrapedTypeItShouldDoNothing(): void
    {
        $listener = \Mockery::mock(ScrapedListener::class);
        App::instance(get_class($listener), $listener);

        $scrapedListener = new ScrapedListener([
            'known_type' => get_class($listener),
        ]);

        $scrapedEvent = new Scraped(
            new ScrapeRequest(
                ':scrape-url:',
                ':type:'
            ),
            new ScrapedData(
                null,
                []
            )
        );

        $listener->shouldNotReceive('handle');

        $scrapedListener->handle($scrapedEvent);
    }

    /**
     * @test
     */
    public function whenReceiveAKnownScrapedTypeItShouldHandleTheEventWithTheSpecificDependency(): void
    {
        $listener = \Mockery::mock(ScrapedListener::class);
        App::instance(get_class($listener), $listener);

        $scrapedListener = new ScrapedListener([
            ':type:' => get_class($listener),
        ]);

        $scrapedEvent = new Scraped(
            new ScrapeRequest(
                ':scrape-url:',
                ':type:'
            ),
            new ScrapedData(
                null,
                []
            )
        );

        $listener->shouldReceive('handle')
            ->once()
            ->with($scrapedEvent);

        $scrapedListener->handle($scrapedEvent);
    }
}

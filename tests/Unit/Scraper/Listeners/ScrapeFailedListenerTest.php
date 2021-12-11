<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Listeners;

use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeFailed;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Tests\TestCase;

class ScrapeFailedListenerTest extends TestCase
{
    /**
     * @test
     */
    public function whenReceiveAnUnknownScrapeFailedTypeItShouldDoNothing(): void
    {
        $listener = \Mockery::mock(ScrapeFailedListener::class);
        \App::instance(get_class($listener), $listener);

        $scrapeFailedListener = new ScrapeFailedListener([
            'known_type' => get_class($listener),
        ]);

        $scrapeFailedEvent = new ScrapeFailed(
            new ScrapeRequest(
                'http://uri',
                'unknown_type'
            ),
            [],
            1
        );

        $listener->shouldNotReceive('handle');

        $scrapeFailedListener->handle($scrapeFailedEvent);
    }

    /**
     * @test
     */
    public function whenReceiveAKnownScrapeFailedTypeItShouldHandleTheEventWithTheSpecificDependency(): void
    {
        $listener = \Mockery::mock(ScrapeFailedListener::class);
        \App::instance(get_class($listener), $listener);

        $scrapeFailedListener = new ScrapeFailedListener([
            'known_type' => get_class($listener),
        ]);

        $scrapeFailedEvent = new ScrapeFailed(
            new ScrapeRequest(
                'http://uri',
                'known_type'
            ),
            [],
            1
        );

        $listener->shouldReceive('handle')
            ->once()
            ->with($scrapeFailedEvent);

        $scrapeFailedListener->handle($scrapeFailedEvent);
    }
}

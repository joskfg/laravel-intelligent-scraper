<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Softonic\LaravelIntelligentScraper\Scraper\Entities\Field;
use Softonic\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Softonic\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Tests\TestCase;

class ScrapedListenerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

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

        Event::assertNotDispatched(ScrapeRequest::class);

        $scrapedListener->handle($scrapedEvent);

        $listener->shouldNotReceive('handle');
    }

    /**
     * @test
     */
    public function whenReceiveAKnownScrapedTypeItShouldTriggerTheScraping(): void
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

        Event::assertNotDispatched(ScrapeRequest::class);
    }

    /**
     * @test
     */
    public function whenReceiveATypeThatShoudlTriggerAScraoeItShouldHandleTheEventWithTheSpecificDependency(): void
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
                [
                    new Field(
                        ':field-name:',
                        ':field-value:',
                        ':chain-type:'
                    ),
                ]
            )
        );

        $listener->shouldReceive('handle')
            ->once()
            ->with($scrapedEvent);

        $scrapedListener->handle($scrapedEvent);

        Event::assertDispatched(
            ScrapeRequest::class,
            fn (ScrapeRequest $event) => $event->url === ':field-value:' && $event->type === ':chain-type:'
        );
    }
}

<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\Field;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
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

    public function chainedScraperProvider(): array
    {
        return [
            'fullUrl' => [
                'scrapeUrl'        => 'https://something.dev',
                'chainedUrl'       => 'https://something.dev/final-endpoint',
                'finalUrlToScrape' => 'https://something.dev/final-endpoint',
            ],
            'partialUrl' => [
                'scrapeUrl'        => 'https://something.dev',
                'chainedUrl'       => '/final-endpoint',
                'finalUrlToScrape' => 'https://something.dev/final-endpoint',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider chainedScraperProvider
     * @param mixed $scrapeUrl
     * @param mixed $chainedUrl
     * @param mixed $finalUrlToScrape
     */
    public function whenReceiveATypeThatShouldTriggerAScrapeItShouldHandleTheEventWithTheSpecificDependency(
        $scrapeUrl,
        $chainedUrl,
        $finalUrlToScrape
    ): void {
        $listener = \Mockery::mock(ScrapedListener::class);
        App::instance(get_class($listener), $listener);

        $scrapedListener = new ScrapedListener([
            ':type:' => get_class($listener),
        ]);

        $scrapedEvent = new Scraped(
            new ScrapeRequest(
                $scrapeUrl,
                ':type:'
            ),
            new ScrapedData(
                null,
                [
                    new Field(
                        ':field-name:',
                        [$chainedUrl],
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
            fn (ScrapeRequest $event) => $event->url === $finalUrlToScrape && $event->type === ':chain-type:'
        );
    }
}

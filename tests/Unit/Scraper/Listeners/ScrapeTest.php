<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Application\XpathFinder;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Joskfg\LaravelIntelligentScraper\Scraper\Exceptions\MissingXpathValueException;
use Joskfg\LaravelIntelligentScraper\Scraper\Repositories\Configuration;
use Mockery;
use Mockery\LegacyMockInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Tests\TestCase;
use UnexpectedValueException;

class ScrapeTest extends TestCase
{
    use DatabaseMigrations;

    private LegacyMockInterface $config;

    private LegacyMockInterface $xpathFinder;

    private string $type;

    private ScrapeRequest $scrapeRequest;

    public function setUp(): void
    {
        parent::setUp();

        Log::spy();

        Event::fake();

        $this->config        = Mockery::mock(Configuration::class);
        $this->xpathFinder   = Mockery::mock(XpathFinder::class);
        $this->type          = 'post';
        $this->scrapeRequest = new ScrapeRequest(':scrape-url:', $this->type);
    }

    /**
     * @test
     */
    public function whenConfigurationDoesNotExistItShouldThrowAnEvent(): void
    {
        $this->config->shouldReceive('findByType')
            ->once()
            ->with($this->type)
            ->andReturn(collect());

        Event::assertNotDispatched(InvalidConfiguration::class);

        $scrape = new Scrape(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );

        $scrape->handle($this->scrapeRequest);
    }

    /**
     * @test
     */
    public function whenScrappingConnectionFailsItShouldThrowAConnectionException(): void
    {
        $xpathConfig = collect([
            ':field-1:' => ':xpath-1:',
            ':field-2:' => ':xpath-2:',
        ]);
        $this->config->shouldReceive('findByType')
            ->once()
            ->with($this->type)
            ->andReturn($xpathConfig);

        $this->xpathFinder->shouldReceive('extract')
            ->once()
            ->with(':scrape-url:', $xpathConfig)
            ->andThrowExceptions([Mockery::mock(TransportException::class)]);

        $this->expectException(TransportException::class);

        $scrape = new Scrape(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );
        $scrape->handle($this->scrapeRequest);
    }

    /**
     * @test
     */
    public function whenTheIdStoreIsNotAvailableItShouldThrowAnUnexpectedValueException(): void
    {
        $xpathConfig = collect([
            ':field-1:' => ':value-1:',
            ':field-2:' => ':value-2:',
        ]);
        $this->config->shouldReceive('findByType')
            ->once()
            ->with($this->type)
            ->andReturn($xpathConfig);

        $this->xpathFinder->shouldReceive('extract')
            ->once()
            ->with(':scrape-url:', $xpathConfig)
            ->andThrow(UnexpectedValueException::class, ':error-message:');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(':error-message:');

        $scrape = new Scrape(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );
        $scrape->handle($this->scrapeRequest);
    }

    /**
     * @test
     */
    public function whenTheDataExtractionWorksItShouldReturnsTheScrapedData(): void
    {
        $scrapedData = new ScrapedData(
            ':variant:',
            [
                ':field-1:' => [':value-1:'],
                ':field-2:' => [':value-2:'],
            ]
        );
        $xpathConfig = collect([
            ':field-1:' => ':xpath-1:',
            ':field-2:' => ':xpath-2:',
        ]);
        $this->config->shouldReceive('findByType')
            ->once()
            ->with($this->type)
            ->andReturn($xpathConfig);

        $this->xpathFinder->shouldReceive('extract')
            ->once()
            ->with(':scrape-url:', $xpathConfig)
            ->andReturn($scrapedData);

        $scrape = new Scrape(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );

        Event::assertNotDispatched(Scraped::class);
        $scrape->handle($this->scrapeRequest);
        
        $firedEvents = collect(Event::dispatched(Scraped::class));
        $event = $firedEvents->each(function ($event) {
            $class = Scraped::class;
            return $event instanceof $class;
        });
        
        self::assertSame(
            $scrapedData,
            $event[0][0]->scrapedData
        );
    }

    /**
     * @test
     */
    public function whenTheScraperConfigIsInvalidItShouldTriggerAnEvent(): void
    {
        $xpathConfig = collect([
            ':field-1:' => ':value-1:',
            ':field-2:' => ':value-2:',
        ]);
        $this->config->shouldReceive('findByType')
            ->once()
            ->with($this->type)
            ->andReturn($xpathConfig);

        $this->xpathFinder->shouldReceive('extract')
            ->once()
            ->with(':scrape-url:', $xpathConfig)
            ->andThrow(MissingXpathValueException::class, ':error:');

        Event::assertNotDispatched(InvalidConfiguration::class);

        $scrape = new Scrape(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );
        $scrape->handle($this->scrapeRequest);
    }
}

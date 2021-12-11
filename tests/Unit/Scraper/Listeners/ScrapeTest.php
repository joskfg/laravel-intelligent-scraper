<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Listeners;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
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

        $this->expectsEvents(InvalidConfiguration::class);

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
            ->andThrowExceptions([Mockery::mock(ConnectException::class)]);

        $this->expectException(ConnectException::class);

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

        $this->expectsEvents(Scraped::class);
        $scrape->handle($this->scrapeRequest);

        /** @var Scraped $event */
        $event = collect($this->firedEvents)->filter(function ($event): bool {
            $class = Scraped::class;

            return $event instanceof $class;
        })->first();

        self::assertSame(
            $scrapedData,
            $event->scrapedData
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

        $this->expectsEvents(InvalidConfiguration::class);

        $scrape = new Scrape(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );
        $scrape->handle($this->scrapeRequest);
    }
}

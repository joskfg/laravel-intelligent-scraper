<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Listeners;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\LegacyMockInterface;
use Softonic\LaravelIntelligentScraper\Scraper\Application\XpathFinder;
use Softonic\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Softonic\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Softonic\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeFailed;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Softonic\LaravelIntelligentScraper\Scraper\Exceptions\ConfigurationException;
use Softonic\LaravelIntelligentScraper\Scraper\Models\Configuration as ConfigurationModel;
use Softonic\LaravelIntelligentScraper\Scraper\Repositories\Configuration;
use Tests\TestCase;
use UnexpectedValueException;

class ConfigureScraperTest extends TestCase
{
    use DatabaseMigrations;

    private LegacyMockInterface $config;

    private LegacyMockInterface $xpathFinder;

    private string $url;

    private string $type;

    public function setUp(): void
    {
        parent::setUp();

        Log::spy();

        $this->config      = Mockery::mock(Configuration::class);
        $this->xpathFinder = Mockery::mock(XpathFinder::class);
        $this->url         = ':scrape-url:';
        $this->type        = ':type:';
    }

    /**
     * @test
     */
    public function whenCannotBeCalculatedItShouldThrowAnException(): void
    {
        $this->config->shouldReceive('calculate')
            ->once()
            ->with($this->type)
            ->andThrow(ConfigurationException::class, ':error:');

        Log::shouldReceive('error')
            ->with(
                "Error scraping ':scrape-url:'",
                ['message' => ':error:']
            );

        $configureScraper = new ConfigureScraper(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );

        $this->expectsEvents(ScrapeFailed::class);

        $scrapeRequest = new ScrapeRequest($this->url, $this->type);
        $configureScraper->handle(new InvalidConfiguration($scrapeRequest));
    }

    /**
     * @test
     */
    public function whenIsCalculatedItShouldReturnExtractedDataAndStoreTheNewConfig(): void
    {
        $xpathConfig = collect([
            new ConfigurationModel([
                'name'   => ':field-1:',
                'xpaths' => [':xpath-1:'],
                'type'   => ':type:',
            ]),
            new ConfigurationModel([
                'name'   => ':field-2:',
                'xpaths' => [':xpath-2:'],
                'type'   => ':type:',
            ]),
        ]);
        $scrapedData = new ScrapedData(
            ':variant:',
            [
                ':field-1:' => [':value-1:'],
                ':field-2:' => [':value-2:'],
            ]
        );

        $this->config->shouldReceive('calculate')
            ->once()
            ->with($this->type)
            ->andReturn($xpathConfig);

        $this->xpathFinder->shouldReceive('extract')
            ->once()
            ->with(':scrape-url:', $xpathConfig)
            ->andReturn($scrapedData);

        $configureScraper = new ConfigureScraper(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );

        $this->expectsEvents(Scraped::class);
        $configureScraper->handle(new InvalidConfiguration(new ScrapeRequest($this->url, $this->type)));

        /** @var Scraped $event */
        $event = collect($this->firedEvents)->filter(function ($event): bool {
            $class = Scraped::class;

            return $event instanceof $class;
        })->first();
        self::assertEquals(
            $scrapedData,
            $event->scrapedData
        );

        $this->assertDatabaseHas(
            'configurations',
            [
                'name'   => ':field-1:',
                'xpaths' => json_encode([':xpath-1:'], JSON_THROW_ON_ERROR),
            ]
        );
        $this->assertDatabaseHas(
            'configurations',
            [
                'name'   => ':field-2:',
                'xpaths' => json_encode([':xpath-2:'], JSON_THROW_ON_ERROR),
            ]
        );
    }

    /**
     * @test
     */
    public function whenScrappingConnectionFailsItShouldThrowAConnectionException(): void
    {
        $xpathConfig = collect([
            new ConfigurationModel([
                'name'   => ':field-1:',
                'xpaths' => [':value-1:'],
                'type'   => ':type:',
            ]),
            new ConfigurationModel([
                'name'   => ':field-2:',
                'xpaths' => [':value-2:'],
                'type'   => ':type:',
            ]),
        ]);
        $this->config->shouldReceive('calculate')
            ->once()
            ->with($this->type)
            ->andReturn($xpathConfig);

        $this->xpathFinder->shouldReceive('extract')
            ->once()
            ->with(':scrape-url:', $xpathConfig)
            ->andThrowExceptions([Mockery::mock(ConnectException::class)]);

        $this->expectException(ConnectException::class);

        $configureScraper = new ConfigureScraper(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );
        $configureScraper->handle(new InvalidConfiguration(new ScrapeRequest($this->url, $this->type)));
    }

    /**
     * @test
     */
    public function whenTheIdStoreIsNotAvailableItShouldThrowAnUnexpectedValueException(): void
    {
        $xpathConfig = collect([
            new ConfigurationModel([
                'name'   => ':field-1:',
                'xpaths' => [':value-1:'],
                'type'   => ':type:',
            ]),
            new ConfigurationModel([
                'name'   => ':field-2:',
                'xpaths' => [':value-2:'],
                'type'   => ':type:',
            ]),
        ]);
        $this->config->shouldReceive('calculate')
            ->once()
            ->with($this->type)
            ->andReturn($xpathConfig);

        $this->xpathFinder->shouldReceive('extract')
            ->once()
            ->with(':scrape-url:', $xpathConfig)
            ->andThrow(UnexpectedValueException::class, ':error:');

        Log::shouldReceive('debug');
        Log::shouldReceive('error')
            ->with("Error scraping ':scrape-url:'", ['message' => ':error:']);
        $this->expectsEvents(ScrapeFailed::class);

        $configureScraper = new ConfigureScraper(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );
        $configureScraper->handle(new InvalidConfiguration(new ScrapeRequest($this->url, $this->type)));
    }
}

<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Application\XpathFinder;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeFailed;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Joskfg\LaravelIntelligentScraper\Scraper\Exceptions\ConfigurationException;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\Configuration as ConfigurationModel;
use Joskfg\LaravelIntelligentScraper\Scraper\Repositories\Configuration;
use Mockery;
use Mockery\LegacyMockInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
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

        Event::fake();

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

        
        $scrapeRequest = new ScrapeRequest($this->url, $this->type);
        $configureScraper->handle(new InvalidConfiguration($scrapeRequest));
        
        Event::assertDispatched(ScrapeFailed::class);
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

        $configureScraper->handle(new InvalidConfiguration(new ScrapeRequest($this->url, $this->type)));
        
        Event::assertDispatched(Scraped::class);

        /** @var Scraped $event */
        $firedEvents = Event::dispatched(Scraped::class);
        
        self::assertSame(
            $scrapedData,
            $firedEvents[0][0]->scrapedData
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
            ->andThrowExceptions([Mockery::mock(TransportException::class)]);

        $this->expectException(TransportException::class);

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

        $configureScraper = new ConfigureScraper(
            $this->config,
            $this->xpathFinder,
            Log::getFacadeRoot()
        );
        
        $configureScraper->handle(new InvalidConfiguration(new ScrapeRequest($this->url, $this->type)));
        
        Event::assertDispatched(ScrapeFailed::class);
    }
}

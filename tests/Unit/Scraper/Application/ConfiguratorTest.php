<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Application;

use DOMElement;
use Goutte\Client;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ConfigurationScraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Exceptions\ConfigurationException;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\Configuration as ConfigurationModel;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use Joskfg\LaravelIntelligentScraper\Scraper\Repositories\Configuration;
use Mockery;
use Mockery\LegacyMockInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Exception\TransportException;
use Tests\TestCase;
use Tests\Unit\Fakes\FakeHttpException;
use UnexpectedValueException;

class ConfiguratorTest extends TestCase
{
    use DatabaseMigrations;

    private LegacyMockInterface $client;

    private LegacyMockInterface $xpathBuilder;

    private LegacyMockInterface $configuration;

    private LegacyMockInterface $variantGenerator;

    private Configurator $configurator;

    public function setUp(): void
    {
        parent::setUp();

        $this->client           = Mockery::mock(Client::class);
        $this->xpathBuilder     = Mockery::mock(XpathBuilder::class);
        $this->configuration    = Mockery::mock(Configuration::class);
        $this->variantGenerator = Mockery::mock(VariantGenerator::class);

        Log::spy();

        $this->configurator = new Configurator(
            $this->client,
            $this->xpathBuilder,
            $this->configuration,
            $this->variantGenerator
        );
    }

    /**
     * @test
     */
    public function whenTryToFindNewXpathButUrlFromDatasetIsNotFoundThrowAnExceptionAndRemoveIt(): void
    {
        $posts = collect([
            new ScrapedDataset([
                'url'    => ':scrape-url:',
                'type'   => ':type:',
                'fields' => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-1:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-2:',
                        'found' => true,
                    ],
                ],
            ]),
        ]);

        $requestException = Mockery::mock(FakeHttpException::class);
        $requestException->shouldReceive('getResponse->getStatusCode')
            ->once()
            ->andReturn(404);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url:'
            )
            ->andThrow($requestException);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with(':type:')
            ->andReturn(collect());

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) ":field-1:,:field-2:" not found.', $e->getMessage());
            $this->assertDatabaseMissing('scraped_datasets', ['url' => ':scrape-url:']);
        }
    }

    /**
     * @test
     */
    public function whenTryToFindNewXpathButUrlFromDatasetIsNotAvailableThrowAnExceptionAndRemoveIt(): void
    {
        $posts = collect([
            new ScrapedDataset([
                'url'    => ':scrape-url:',
                'type'   => ':type:',
                'fields' => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-1:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-2:',
                        'found' => true,
                    ],
                ],
            ]),
        ]);

        $connectException = Mockery::mock(TransportException::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url:'
            )
            ->andThrows($connectException);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with(':type:')
            ->andReturn(collect());

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) ":field-1:,:field-2:" not found.', $e->getMessage());
            $this->assertDatabaseMissing('scraped_datasets', ['url' => ':scrape-url:']);
        }
    }

    /**
     * @test
     */
    public function whenTryToFindNewXpathButNotFoundItShouldLogItAndResetVariant(): void
    {
        $posts = collect([
            new ScrapedDataset([
                'url'     => ':scrape-url:',
                'type'    => ':type:',
                'variant' => ':variant:',
                'fields'  => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-1:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-2:',
                        'found' => true,
                    ],
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url:'
            )
            ->andReturn($crawler);

        $rootElement = new DOMElement('test');
        $crawler->shouldReceive('getUri')
            ->andReturn(':scrape-url:');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);

        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-1:')
            ->andReturn(':xpath-1:');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-2:')
            ->andThrow(UnexpectedValueException::class);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with(':type:')
            ->andReturn(collect());

        $this->variantGenerator->shouldReceive('addConfig')
            ->withAnyArgs();
        $this->variantGenerator->shouldReceive('fieldNotFound')
            ->once();
        $this->variantGenerator->shouldReceive('getId')
            ->andReturn('');

        Log::shouldReceive('warning')
            ->with("Field ':field-2:' with value ':value-2:' not found for ':scrape-url:'.");

        $this->expectsEvents(ConfigurationScraped::class);

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) ":field-1:" not found.', $e->getMessage());
        }
    }


    /**
     * @test
     */
    public function whenTryToFindNewXpathButNotFieldWereFoundInTheDatasetItShouldLogItDoNothing(): void
    {
        $posts = collect([
            new ScrapedDataset([
                'url'     => ':scrape-url:',
                'type'    => ':type:',
                'variant' => ':variant:',
                'fields'  => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-1:',
                        'found' => false,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-2:',
                        'found' => false,
                    ],
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url:'
            )
            ->andReturn($crawler);

        $rootElement = new DOMElement('test');
        $crawler->shouldReceive('getUri')
            ->andReturn(':scrape-url:');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);

        $this->xpathBuilder->shouldNotReceive('find');

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with(':type:')
            ->andReturn(collect());

        $this->variantGenerator->shouldNotReceive('addConfig');
        $this->variantGenerator->shouldReceive('getId')
            ->andReturn('');

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) ":field-1:,:field-2:" not found.', $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function whenUseSomeOldXpathButNotFoundNewsItShouldLogItAndResetVariant(): void
    {
        $posts = collect([
            new ScrapedDataset([
                'url'     => ':scrape-url:',
                'type'    => ':type:',
                'variant' => ':variant:',
                'fields'  => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-1:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-2:',
                        'found' => true,
                    ],
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url:'
            )
            ->andReturn($crawler);

        $rootElement = new DOMElement('test');
        $crawler->shouldReceive('getUri')
            ->andReturn(':scrape-url:');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);
        $filter = $crawler->shouldReceive('filterXpath')
            ->andReturnSelf()
            ->getMock()
            ->shouldReceive('count')
            ->once()
            ->andReturn(1);
        $this->xpathBuilder->shouldReceive('find')
            ->never()
            ->with($rootElement, ':value-1:');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-2:')
            ->andThrow(UnexpectedValueException::class);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with(':type:')
            ->andReturn(collect([
                ConfigurationModel::create([
                    'name'   => ':field-1:',
                    'type'   => ':type:',
                    'xpaths' => [':xpath-1:'],
                ]),
            ]));

        $this->variantGenerator->shouldReceive('addConfig')
            ->withAnyArgs();
        $this->variantGenerator->shouldReceive('fieldNotFound')
            ->once();
        $this->variantGenerator->shouldReceive('getId')
            ->andReturn('');

        Log::shouldReceive('warning')
            ->with("Field ':field-2:' with value ':value-2:' not found for ':scrape-url:'.");

        $this->expectsEvents(ConfigurationScraped::class);

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) ":field-1:" not found.', $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function whenTryToFindXpathInMultiplePostsAndNotFoundInAnyItShouldThrowAnExceptionAndLogItAndResetVariant(): void
    {
        $posts = collect([
            new ScrapedDataset([
                'url'     => ':scrape-url-1:',
                'type'    => ':type:',
                'variant' => ':variant:',
                'fields'  => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-1:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-2:',
                        'found' => true,
                    ],
                ],
            ]),
            ScrapedDataset::make([
                'url'     => ':scrape-url-2:',
                'type'    => ':type:',
                'variant' => ':variant:',
                'fields'  => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-3:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-4:',
                        'found' => true,
                    ],
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url-1:'
            )
            ->andReturn($crawler);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url-2:'
            )
            ->andReturn($crawler);

        $rootElement = new DOMElement('test');
        $crawler->shouldReceive('getUri')
            ->andReturn(':scrape-url-1:');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);

        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-1:')
            ->andThrow(UnexpectedValueException::class);
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-2:')
            ->andThrow(UnexpectedValueException::class);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with(':type:')
            ->andReturn(collect());

        $this->variantGenerator->shouldReceive('addConfig')
            ->never();
        $this->variantGenerator->shouldReceive('fieldNotFound')
            ->times(4);
        $this->variantGenerator->shouldReceive('getId')
            ->andReturn('');

        Log::shouldReceive('warning')
            ->with("Field ':field-1:' with value ':value-1:' not found for ':scrape-url-1:'.");

        Log::shouldReceive('warning')
            ->with("Field ':field-2:' with value ':value-2:' not found for ':scrape-url-1:'.");

        $this->expectsEvents(ConfigurationScraped::class);

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) ":field-1:,:field-2:" not found.', $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function whenDiscoverDifferentXpathItShouldGetAllOfThemAndUpdateTheVariants(): void
    {
        $posts = collect([
            new ScrapedDataset([
                'url'     => ':scrape-url-1:',
                'type'    => ':type:',
                'variant' => ':variant:',
                'fields'  => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-1:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-2:',
                        'found' => true,
                    ],
                ],
            ]),
            ScrapedDataset::make([
                'url'     => ':scrape-url-2:',
                'type'    => ':type:',
                'variant' => ':variant:',
                'fields'  => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-1:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-2:',
                        'found' => true,
                    ],
                ],
            ]),
            ScrapedDataset::make([
                'url'     => ':scrape-url-3:',
                'type'    => ':type:',
                'variant' => ':variant:',
                'fields'  => [
                    [
                        'key'   => ':field-1:',
                        'value' => ':value-3:',
                        'found' => true,
                    ],
                    [
                        'key'   => ':field-2:',
                        'value' => ':value-4:',
                        'found' => true,
                    ],
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url-1:'
            )
            ->andReturn($crawler);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url-2:'
            )
            ->andReturn($crawler);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                ':scrape-url-3:'
            )
            ->andReturn($crawler);

        $rootElement = new \DOMElement('test');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);

        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-1:')
            ->andReturn(':xpath-1:');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-2:')
            ->andReturn(':xpath-2:');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-3:')
            ->andReturn(':xpath-3:');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, ':value-4:')
            ->andReturn(':xpath-4:');

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with(':type:')
            ->andReturn(collect());

        $this->variantGenerator->shouldReceive('addConfig')
            ->withAnyArgs();
        $this->variantGenerator->shouldReceive('fieldNotFound')
            ->never();
        $this->variantGenerator->shouldReceive('getId')
            ->andReturn(10, 20, 30);

        $this->expectsEvents(ConfigurationScraped::class);

        $configurations = $this->configurator->configureFromDataset($posts);

        self::assertInstanceOf(ConfigurationModel::class, $configurations[0]);
        self::assertEquals(':field-1:', $configurations[0]['name']);
        self::assertEquals(':type:', $configurations[0]['type']);
        self::assertEquals(
            [
                ':xpath-1:',
                ':xpath-3:',
            ],
            array_values($configurations[0]['xpaths'])
        );

        self::assertInstanceOf(ConfigurationModel::class, $configurations[1]);
        self::assertEquals(':field-2:', $configurations[1]['name']);
        self::assertEquals(':type:', $configurations[1]['type']);
        self::assertEquals(
            [
                ':xpath-2:',
                ':xpath-4:',
            ],
            array_values($configurations[1]['xpaths'])
        );
    }
}

<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Application;

use Goutte\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Mockery;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ConfigurationScraped;
use Softonic\LaravelIntelligentScraper\Scraper\Exceptions\ConfigurationException;
use Softonic\LaravelIntelligentScraper\Scraper\Models\Configuration as ConfigurationModel;
use Softonic\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use Softonic\LaravelIntelligentScraper\Scraper\Repositories\Configuration;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class ConfiguratorTest extends TestCase
{
    use DatabaseMigrations;

    private \Mockery\LegacyMockInterface $client;

    private \Mockery\LegacyMockInterface $xpathBuilder;

    private \Mockery\LegacyMockInterface $configuration;

    private \Mockery\LegacyMockInterface $variantGenerator;

    private \Softonic\LaravelIntelligentScraper\Scraper\Application\Configurator $configurator;

    public function setUp(): void
    {
        parent::setUp();

        $this->client           = \Mockery::mock(Client::class);
        $this->xpathBuilder     = \Mockery::mock(XpathBuilder::class);
        $this->configuration    = \Mockery::mock(Configuration::class);
        $this->variantGenerator = \Mockery::mock(VariantGenerator::class);

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
                'url'  => 'https://test.c/123456789012',
                'type' => 'post',
                'data' => [
                    'title'  => 'My Title',
                    'author' => 'My author',
                ],
            ]),
        ]);

        $requestException = \Mockery::mock(RequestException::class);
        $requestException->shouldReceive('getResponse->getStatusCode')
            ->once()
            ->andReturn(404);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789012'
            )
            ->andThrows($requestException);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with('post')
            ->andReturn(collect());

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) "title,author" not found.', $e->getMessage());
            $this->assertDatabaseMissing('scraped_datasets', ['url' => 'https://test.c/123456789012']);
        }
    }

    /**
     * @test
     */
    public function whenTryToFindNewXpathButUrlFromDatasetIsNotAvailableThrowAnExceptionAndRemoveIt(): void
    {
        $posts = collect([
            new ScrapedDataset([
                'url'  => 'https://test.c/123456789012',
                'type' => 'post',
                'data' => [
                    'title'  => 'My Title',
                    'author' => 'My author',
                ],
            ]),
        ]);

        $connectException = \Mockery::mock(ConnectException::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789012'
            )
            ->andThrows($connectException);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with('post')
            ->andReturn(collect());

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) "title,author" not found.', $e->getMessage());
            $this->assertDatabaseMissing('scraped_datasets', ['url' => 'https://test.c/123456789012']);
        }
    }

    /**
     * @test
     */
    public function whenTryToFindNewXpathButNotFoundItShouldLogItAndResetVariant(): void
    {
        $posts = collect([
            ScrapedDataset::create([
                'url'     => 'https://test.c/123456789012',
                'type'    => 'post',
                'variant' => 'f45a8de53eaeea347a83ebaafaf29f16a1dd97e0',
                'data'    => [
                    'title'  => 'My Title',
                    'author' => 'My author',
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789012'
            )
            ->andReturn($crawler);

        $rootElement = new \DOMElement('test');
        $crawler->shouldReceive('getUri')
            ->andReturn('https://test.c/123456789012');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);

        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My Title')
            ->andReturn('//*[|id="title"]');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My author')
            ->andThrow(\UnexpectedValueException::class);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with('post')
            ->andReturn(collect());

        $this->variantGenerator->shouldReceive('addConfig')
            ->withAnyArgs();
        $this->variantGenerator->shouldReceive('fieldNotFound')
            ->once();
        $this->variantGenerator->shouldReceive('getId')
            ->andReturn('');

        Log::shouldReceive('warning')
            ->with("Field 'author' with value 'My author' not found for 'https://test.c/123456789012'.");

        $this->expectsEvents(ConfigurationScraped::class);

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) "author" not found.', $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function whenUseSomeOldXpathButNotFoundNewsItShouldLogItAndResetVariant(): void
    {
        $posts = collect([
            ScrapedDataset::create([
                'url'     => 'https://test.c/123456789012',
                'type'    => 'post',
                'variant' => 'f45a8de53eaeea347a83ebaafaf29f16a1dd97e0',
                'data'    => [
                    'title'  => 'My Title',
                    'author' => 'My author',
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789012'
            )
            ->andReturn($crawler);

        $rootElement = new \DOMElement('test');
        $crawler->shouldReceive('getUri')
            ->andReturn('https://test.c/123456789012');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);
        $crawler->shouldReceive('filterXpath->count')
            ->once()
            ->andReturn(1);

        $this->xpathBuilder->shouldReceive('find')
            ->never()
            ->with($rootElement, 'My Title');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My author')
            ->andThrow(\UnexpectedValueException::class);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with('post')
            ->andReturn(collect([
                ConfigurationModel::create([
                    'name' => 'title',
                    'type' => 'post',
                    'xpaths' => ['//*[|id="title"]'],
                ]),
            ]));

        $this->variantGenerator->shouldReceive('addConfig')
            ->withAnyArgs();
        $this->variantGenerator->shouldReceive('fieldNotFound')
            ->once();
        $this->variantGenerator->shouldReceive('getId')
            ->andReturn('');

        Log::shouldReceive('warning')
            ->with("Field 'author' with value 'My author' not found for 'https://test.c/123456789012'.");

        $this->expectsEvents(ConfigurationScraped::class);

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) "author" not found.', $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function whenTryToFindXpathInMultiplePostsAndNotFoundInAnyItShouldThrowAnExceptionAndLogItAndResetVariant(): void
    {
        $posts = collect([
            ScrapedDataset::make([
                'url'     => 'https://test.c/123456789012',
                'type'    => 'post',
                'variant' => 'f45a8de53eaeea347a83ebaafaf29f16a1dd97e0',
                'data'    => [
                    'title'  => 'My Title',
                    'author' => 'My author',
                ],
            ]),
            ScrapedDataset::make([
                'url'     => 'https://test.c/123456789022',
                'type'    => 'post',
                'variant' => 'f45a8de53eaeea347a83ebaafaf29f16a1dd97e0',
                'data'    => [
                    'title'  => 'My Title',
                    'author' => 'My author',
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789012'
            )
            ->andReturn($crawler);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789022'
            )
            ->andReturn($crawler);

        $rootElement = new \DOMElement('test');
        $crawler->shouldReceive('getUri')
            ->andReturn('https://test.c/123456789012');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);

        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My Title')
            ->andThrow(\UnexpectedValueException::class);
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My author')
            ->andThrow(\UnexpectedValueException::class);

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with('post')
            ->andReturn(collect());

        $this->variantGenerator->shouldReceive('addConfig')
            ->never();
        $this->variantGenerator->shouldReceive('fieldNotFound')
            ->times(4);
        $this->variantGenerator->shouldReceive('getId')
            ->andReturn('');

        Log::shouldReceive('warning')
            ->with("Field 'title' with value 'My Title' not found for 'https://test.c/123456789012'.");

        Log::shouldReceive('warning')
            ->with("Field 'author' with value 'My author' not found for 'https://test.c/123456789012'.");

        $this->expectsEvents(ConfigurationScraped::class);

        try {
            $this->configurator->configureFromDataset($posts);
        } catch (ConfigurationException $e) {
            self::assertEquals('Field(s) "title,author" not found.', $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function whenDiscoverDifferentXpathItShouldGetAllOfThemAndUpdateTheVariants(): void
    {
        $posts = collect([
            ScrapedDataset::make([
                'url'     => 'https://test.c/123456789012',
                'type'    => 'post',
                'variant' => 'f45a8de53eaeea347a83ebaafaf29f16a1dd97e0',
                'data'    => [
                    'title'  => 'My Title',
                    'author' => 'My author',
                ],
            ]),
            ScrapedDataset::make([
                'url'     => 'https://test.c/123456789022',
                'type'    => 'post',
                'variant' => 'f45a8de53eaeea347a83ebaafaf29f16a1dd97e0',
                'data'    => [
                    'title'  => 'My Title',
                    'author' => 'My author',
                ],
            ]),
            ScrapedDataset::make([
                'url'     => 'https://test.c/123456789033',
                'type'    => 'post',
                'variant' => 'f45a8de53eaeea347a83ebaafaf29f16a1dd97e0',
                'data'    => [
                    'title'  => 'My Title2',
                    'author' => 'My author2',
                ],
            ]),
        ]);

        $crawler = Mockery::mock(Crawler::class);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789012'
            )
            ->andReturn($crawler);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789022'
            )
            ->andReturn($crawler);
        $this->client->shouldReceive('request')
            ->once()
            ->with(
                'GET',
                'https://test.c/123456789033'
            )
            ->andReturn($crawler);

        $rootElement = new \DOMElement('test');
        $crawler->shouldReceive('getNode')
            ->with(0)
            ->andReturn($rootElement);

        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My Title')
            ->andReturn('//*[|id="title"]');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My author')
            ->andReturn('//*[|id="author"]');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My Title2')
            ->andReturn('//*[|id="title2"]');
        $this->xpathBuilder->shouldReceive('find')
            ->with($rootElement, 'My author2')
            ->andReturn('//*[|id="author2"]');

        $this->configuration->shouldReceive('findByType')
            ->once()
            ->with('post')
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
        self::assertEquals('title', $configurations[0]['name']);
        self::assertEquals('post', $configurations[0]['type']);
        self::assertEquals(
            [
                '//*[|id="title"]',
                '//*[|id="title2"]',
            ],
            array_values($configurations[0]['xpaths'])
        );

        self::assertInstanceOf(ConfigurationModel::class, $configurations[1]);
        self::assertEquals('author', $configurations[1]['name']);
        self::assertEquals('post', $configurations[1]['type']);
        self::assertEquals(
            [
                '//*[|id="author"]',
                '//*[|id="author2"]',
            ],
            array_values($configurations[1]['xpaths'])
        );
    }
}

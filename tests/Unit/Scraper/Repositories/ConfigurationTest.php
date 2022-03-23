<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Repositories;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Application\Configurator;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\Configuration as ConfigurationModel;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setUp();

        Log::spy();
    }

    /**
     * @test
     */
    public function whenRetrieveAllConfigurationItShouldReturnIt(): void
    {
        ConfigurationModel::create([
            'name'   => ':field-1:',
            'type'   => ':type-1:',
            'xpaths' => ':xpath-1:',
        ]);
        ConfigurationModel::create([
            'name'   => ':field-2:',
            'type'   => ':type-2:',
            'xpaths' => ':xpath-2:',
        ]);
        ConfigurationModel::create([
            'name'   => ':field-3:',
            'type'   => ':type-1:',
            'xpaths' => ':xpath-3:',
        ]);

        $configuration = new Configuration();
        $data          = $configuration->findByType(':type-1:');

        self::assertCount(2, $data);
    }

    /**
     * @test
     */
    public function whenRecalculateButThereIsNotAType1DatasetItShouldThrowAnException(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('A dataset example is needed to recalculate xpaths for type :type-1:.');

        $configurator = \Mockery::mock(Configurator::class);
        App::instance(Configurator::class, $configurator);

        $configuration = new Configuration();
        $configuration->calculate(':type-1:');
    }

    /**
     * @test
     */
    public function whenRecalculateItShouldStoreTheNewXpaths(): void
    {
        ScrapedDataset::create([
            'url_hash' => hash('sha256', 'https://test.c/123456789222'),
            'url'     => 'https://test.c/123456789222',
            'type'    => ':type-1:',
            'variant' => 'b265521fc089ac61b794bfa3a5ce8a657f6833ce',
            'fields'  => [
                [
                    'key'   => ':field-1:',
                    'value' => ':value-1:',
                    'found' => true,
                ],
                [
                    'key'   => ':field-3:',
                    'value' => ':value-2:',
                    'found' => true,
                ],
            ],
        ]);
        ScrapedDataset::create([
            'url_hash' => hash('sha256', 'https://test.c/7675487989076'),
            'url'     => 'https://test.c/7675487989076',
            'type'    => ':type-2:',
            'variant' => 'b265521fc089ac61b794bfa3a5ce8a657f6833ce',
            'fields'  => [
                [
                    'key'   => ':field-2:',
                    'value' => ':value-3:',
                    'found' => true,
                ],
                [
                    'key'   => ':field-3:',
                    'value' => ':value-2:',
                    'found' => true,
                ],
            ],
        ]);
        ScrapedDataset::create([
            'url_hash' => hash('sha256', 'https://test.c/223456789111'),
            'url'     => 'https://test.c/223456789111',
            'type'    => ':type-1:',
            'variant' => 'b265521fc089ac61b794bfa3a5ce8a657f6833ce',
            'fields'  => [
                [
                    'key'   => ':field-1:',
                    'value' => ':value-4:',
                    'found' => true,
                ],
                [
                    'key'   => ':field-3:',
                    'value' => ':value-2:',
                    'found' => true,
                ],
            ],
        ]);

        $config = collect([
            ConfigurationModel::make([
                'name'   => ':field-1:',
                'type'   => ':type-1:',
                'xpaths' => ':xpath-1:',
            ]),
            ConfigurationModel::make([
                'name'   => ':field-3:',
                'type'   => ':type-1:',
                'xpaths' => ':xpath-3:',
            ]),
        ]);

        Cache::shouldReceive('get')
            ->with(Configuration::class . '-config-:type-1:')
            ->andReturnNull();
        Cache::shouldReceive('put')
            ->with(Configuration::class . '-config-:type-1:', $config, Configuration::CACHE_TTL);

        $configurator = \Mockery::mock(Configurator::class);
        App::instance(Configurator::class, $configurator);
        $configurator->shouldReceive('configureFromDataset')
            ->withArgs(fn ($typeOnes) => 2 === $typeOnes->count())
            ->andReturn($config);

        $configuration = new Configuration();
        $configs       = $configuration->calculate(':type-1:');

        self::assertEquals(':field-1:', $configs[0]['name']);
        self::assertEquals(':type-1:', $configs[0]['type']);
        self::assertEquals(':xpath-1:', $configs[0]['xpaths'][0]);
        self::assertEquals(':field-3:', $configs[1]['name']);
        self::assertEquals(':type-1:', $configs[1]['type']);
        self::assertEquals(':xpath-3:', $configs[1]['xpaths'][0]);
    }

    /**
     * @test
     */
    public function whenRecalculateFailsItShouldThrowAnException(): void
    {
        ScrapedDataset::create([
            'url_hash' => hash('sha256', 'https://test.c/123456789222'),
            'url'     => 'https://test.c/123456789222',
            'type'    => ':type-1:',
            'variant' => 'b265521fc089ac61b794bfa3a5ce8a657f6833ce',
            'fields'  => [
                [
                    'key'   => ':field-1:',
                    'value' => ':value-1:',
                    'found' => true,
                ],
                [
                    'key'   => ':field-3:',
                    'value' => ':value-2:',
                    'found' => true,
                ],
            ],
        ]);
        ScrapedDataset::create([
            'url_hash' => hash('sha256', 'https://test.c/7675487989076'),
            'url'     => 'https://test.c/7675487989076',
            'type'    => ':type-2:',
            'variant' => 'b265521fc089ac61b794bfa3a5ce8a657f6833ce',
            'fields'  => [
                [
                    'key'   => ':field-2:',
                    'value' => ':value-3:',
                    'found' => true,
                ],
                [
                    'key'   => ':field-3:',
                    'value' => ':value-2:',
                    'found' => true,
                ],
            ],
        ]);
        ScrapedDataset::create([
            'url_hash' => hash('sha256', 'https://test.c/223456789111'),
            'url'     => 'https://test.c/223456789111',
            'type'    => ':type-1:',
            'variant' => 'b265521fc089ac61b794bfa3a5ce8a657f6833ce',
            'fields'  => [
                [
                    'key'   => ':field-1:',
                    'value' => ':value-4:',
                    'found' => true,
                ],
                [
                    'key'   => ':field-3:',
                    'value' => ':value-2:',
                    'found' => true,
                ],
            ],
        ]);

        Cache::shouldReceive('get')
            ->with(Configuration::class . '-config-:type-1:')
            ->andReturnNull();

        $configurator = \Mockery::mock(Configurator::class);
        App::instance(Configurator::class, $configurator);
        $configurator->shouldReceive('configureFromDataset')
            ->withArgs(fn ($typeOnes) => 2 === $typeOnes->count())
            ->andThrow(new \UnexpectedValueException('Recalculate fail'));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Recalculate fail');

        $configuration = new Configuration();
        $configuration->calculate(':type-1:');
    }

    /**
     * @test
     */
    public function whenCalculateAfterAnotherCalculateItShouldUseThePrecalculatedConfig(): void
    {
        $configurator = \Mockery::mock(Configurator::class);
        App::instance(Configurator::class, $configurator);
        $configurator->shouldReceive('configureFromDataset')
            ->never();

        $config = collect('configuration');

        Cache::shouldReceive('get')
            ->with(Configuration::class . '-config-:type-1:')
            ->andReturn($config);

        $configuration = new Configuration();
        self::assertEquals(
            $config,
            $configuration->calculate(':type-1:')
        );
    }
}

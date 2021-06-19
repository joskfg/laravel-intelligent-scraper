<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;
use Softonic\LaravelIntelligentScraper\Scraper\Application\Configurator;
use Softonic\LaravelIntelligentScraper\Scraper\Models\Configuration as ConfigurationModel;
use Softonic\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use UnexpectedValueException;

class Configuration
{
    /**
     * @var Configurator
     */
    private Configurator $configurator;

    /**
     * Cache TTL in seconds.
     *
     * This is the time between config calculations.
     */
    public const CACHE_TTL = 1800;

    public function findByType(string $type): Collection
    {
        return ConfigurationModel::withType($type)->get();
    }

    /**
     * @throws JsonException
     */
    public function calculate(string $type): Collection
    {
        $this->configurator = $this->configurator ?? resolve(Configurator::class);

        $cacheKey = $this->getCacheKey($type);
        $config   = Cache::get($cacheKey);
        if (!$config) {
            Log::warning('Calculating configuration');
            $scrapedDataset = ScrapedDataset::withType($type)->get();

            if ($scrapedDataset->isEmpty()) {
                throw new UnexpectedValueException("A dataset example is needed to recalculate xpaths for type $type.");
            }

            $config = $this->configurator->configureFromDataset($scrapedDataset);
            Cache::put($cacheKey, $config, self::CACHE_TTL);
        }

        return $config;
    }

    protected function getCacheKey(string $type): string
    {
        return self::class . "-config-$type";
    }
}

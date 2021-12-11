<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Application\Configurator;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\Configuration as ConfigurationModel;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use JsonException;
use UnexpectedValueException;

class Configuration
{
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
        $this->configurator ??= resolve(Configurator::class);

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

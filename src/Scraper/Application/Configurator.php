<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Application;

use Goutte\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\Field;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ConfigurationScraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Joskfg\LaravelIntelligentScraper\Scraper\Exceptions\ConfigurationException;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\Configuration;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use Joskfg\LaravelIntelligentScraper\Scraper\Repositories\Configuration as ConfigurationRepository;
use JsonException;
use Symfony\Component\DomCrawler\Crawler;
use UnexpectedValueException;

class Configurator
{
    private Client $client;

    private XpathBuilder $xpathBuilder;

    private VariantGenerator $variantGenerator;

    private ConfigurationRepository $configuration;

    public function __construct(
        Client $client,
        XpathBuilder $xpathBuilder,
        ConfigurationRepository $configuration,
        VariantGenerator $variantGenerator
    ) {
        $this->client           = $client;
        $this->xpathBuilder     = $xpathBuilder;
        $this->variantGenerator = $variantGenerator;
        $this->configuration    = $configuration;
    }

    public function configureFromDataset(Collection $scrapedDataset): Collection
    {
        $type                 = $scrapedDataset->first()->getAttribute('type');
        $currentConfiguration = $this->configuration->findByType($type);

        $totalDatasets = $scrapedDataset->count();

        $result = $scrapedDataset->map(function ($scrapedData, $key) use ($totalDatasets, $currentConfiguration) {
            Log::info("Finding config $key/$totalDatasets");
            if ($crawler = $this->getCrawler($scrapedData)) {
                return $this->findConfigByScrapedData($scrapedData, $crawler, $currentConfiguration);
            }
        })->filter();

        $finalConfig = $this->mergeConfiguration($result->toArray(), $type);

        $this->checkConfiguration($scrapedDataset[0]['fields'], $finalConfig);

        return $finalConfig;
    }

    private function getCrawler(ScrapedDataset $scrapedData): ?Crawler
    {
        try {
            Log::info("Request {$scrapedData['url']}");

            return $this->client->request('GET', $scrapedData['url']);
        } catch (ConnectException $e) {
            Log::notice(
                "Connection error: {$e->getMessage()}",
                compact('scrapedData')
            );
            $scrapedData->delete();
        } catch (RequestException $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            Log::notice(
                "Response status ($httpCode) invalid, so proceeding to delete the scraped data.",
                compact('scrapedData')
            );
            $scrapedData->delete();
        }

        return null;
    }

    /**
     * Tries to find a new config.
     *
     * If the data is not valid anymore, it is deleted from dataset.
     */
    private function findConfigByScrapedData(ScrapedDataset $scrapedData, Crawler $crawler, Collection $currentConfiguration): array
    {
        $result = [];

        foreach ($scrapedData['fields'] as $field) {
            if (!$field['found']) {
                continue;
            }

            $field = new Field(
                $field['key'],
                $field['value'],
                $field['found'],
            );
            try {
                Log::info("Searching xpath for field {$field->getKey()}");
                $result[$field->getKey()] = $this->getOldXpath($currentConfiguration, $field->getKey(), $crawler);
                if (!$result[$field->getKey()]) {
                    Log::debug('Trying to find a new xpath.');
                    $result[$field->getKey()] = $this->xpathBuilder->find(
                        $crawler->getNode(0),
                        $field->getValue()
                    );
                }
                $this->variantGenerator->addConfig($field->getKey(), $result[$field->getKey()]);
                Log::info('Added found xpath to the config');
            } catch (UnexpectedValueException $e) {
                $this->variantGenerator->fieldNotFound();
                try {
                    $value = is_array($field->getValue()) ? json_encode($field->getValue(), JSON_THROW_ON_ERROR) : $field->getValue();
                } catch (JsonException $e) {
                }
                Log::notice("Field '{$field->getKey()}' with value '{$value}' not found for '{$crawler->getUri()}'.");
            }
        }

        event(new ConfigurationScraped(
            new ScrapeRequest(
                $scrapedData['url'],
                $scrapedData['type']
            ),
            new ScrapedData(
                $this->variantGenerator->getId($scrapedData['type']),
                $scrapedData['fields'],
            )
        ));

        return $result;
    }

    private function getOldXpath($currentConfiguration, $field, $crawler)
    {
        Log::debug('Checking old Xpaths');
        $config = $currentConfiguration->firstWhere('name', $field);
        foreach ($config['xpaths'] ?? [] as $xpath) {
            Log::debug("Checking xpath $xpath");
            $isFound = $crawler->filterXPath($xpath)->count();
            if ($isFound) {
                return $xpath;
            }
        }

        Log::debug('Old xpath not found');

        return false;
    }

    /**
     * Merge configuration.
     *
     * Assign to a field all the possible Xpath.
     */
    private function mergeConfiguration(array $result, string $type): Collection
    {
        $fieldConfig = [];
        foreach ($result as $configs) {
            foreach ($configs as $field => $configurations) {
                $fieldConfig[$field][] = $configurations;
            }
        }

        $finalConfig = collect();
        foreach ($fieldConfig as $field => $xpaths) {
            $finalConfig[] = Configuration::firstOrNew(
                ['name' => $field],
                [
                    'type'   => $type,
                    'xpaths' => array_unique($xpaths),
                ]
            );
        }

        return $finalConfig;
    }

    private function checkConfiguration($fields, Collection $finalConfig): void
    {
        $fields = collect($fields);
        if ($finalConfig->count() !== $fields->count()) {
            $fieldsMissing = $fields
                ->pluck('key')
                ->diff($finalConfig->pluck('name'))
                ->implode(',');

            throw new ConfigurationException("Field(s) \"$fieldsMissing\" not found.", 0);
        }
    }
}

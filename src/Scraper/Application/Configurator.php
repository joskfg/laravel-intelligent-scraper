<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Application;

use Goutte\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JsonException;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ConfigurationScraped;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Softonic\LaravelIntelligentScraper\Scraper\Exceptions\ConfigurationException;
use Softonic\LaravelIntelligentScraper\Scraper\Models\Configuration;
use Softonic\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use Softonic\LaravelIntelligentScraper\Scraper\Repositories\Configuration as ConfigurationRepository;
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
        $type                 = $scrapedDataset[0]['type'];
        $currentConfiguration = $this->configuration->findByType($type);

        $result        = [];
        $totalDatasets = count($scrapedDataset);
        foreach ($scrapedDataset as $key => $scrapedData) {
            Log::info("Finding config $key/$totalDatasets");
            if ($crawler = $this->getCrawler($scrapedData)) {
                $result[] = $this->findConfigByScrapedData($scrapedData, $crawler, $currentConfiguration);
            }
        }

        $finalConfig = $this->mergeConfiguration($result, $type);

        $this->checkConfiguration($scrapedDataset[0]['data'], $finalConfig);

        return $finalConfig;
    }

    private function getCrawler($scrapedData): ?Crawler
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

        foreach ($scrapedData['data'] as $field => $value) {
            try {
                Log::info("Searching xpath for field $field");
                $result[$field] = $this->getOldXpath($currentConfiguration, $field, $crawler);
                if (!$result[$field]) {
                    Log::debug('Trying to find a new xpath.');
                    $result[$field] = $this->xpathBuilder->find(
                        $crawler->getNode(0),
                        $value
                    );
                }
                $this->variantGenerator->addConfig($field, $result[$field]);
                Log::info('Added found xpath to the config');
            } catch (UnexpectedValueException $e) {
                $this->variantGenerator->fieldNotFound();
                try {
                    $value = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
                } catch (JsonException $e) {
                }
                Log::notice("Field '$field' with value '$value' not found for '{$crawler->getUri()}'.");
            }
        }

        event(new ConfigurationScraped(
            new ScrapeRequest(
                $scrapedData['url'],
                $scrapedData['type']
            ),
            $scrapedData['data'],
            $this->variantGenerator->getId($scrapedData['type'])
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

    private function checkConfiguration($data, Collection $finalConfig): void
    {
        if (count($finalConfig) !== count($data)) {
            $fieldsFound    = $finalConfig->pluck('name')->toArray();
            $fieldsExpected = array_keys($data);

            $fieldsMissing = implode(',', array_diff($fieldsExpected, $fieldsFound));
            throw new ConfigurationException("Field(s) \"$fieldsMissing\" not found.", 0);
        }
    }
}

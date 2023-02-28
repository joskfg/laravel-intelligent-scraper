<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Application;

use Goutte\Client as GoutteClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\Field;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Joskfg\LaravelIntelligentScraper\Scraper\Exceptions\MissingXpathValueException;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\Configuration;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use UnexpectedValueException;

class XpathFinder
{
    private GoutteClient $client;

    private VariantGenerator $variantGenerator;

    public function __construct(GoutteClient $client, VariantGenerator $variantGenerator)
    {
        $this->client           = $client;
        $this->variantGenerator = $variantGenerator;
    }

    public function extract(string $url, Collection $configs): ScrapedData
    {
        $crawler = $this->getCrawler($url);

        Log::info('Response Received. Start crawling.');
        $scrapedData = new ScrapedData();

        foreach ($configs as $config) {
            Log::info(
                'Searching field',
                [
                    'field' => $config->getAttribute('name'),
                ]
            );
            $value = $this->extractValue($config, $crawler);

            if (!$config->getAttribute('optional') && $value === null) {
                $missingXpath = implode('\', \'', $config->getAttribute('xpaths'));
                throw new MissingXpathValueException(
                    "Xpath '$missingXpath' for field '{$config->getAttribute('name')}' not found in '$url'."
                );
            }

            $scrapedData->setField(
                new Field(
                    $config->getAttribute('name'),
                    $value ?? $config->getAttribute('default'),
                    $config->getAttribute('chain_type'),
                    $value !== null,
                )
            );
        }

        Log::info('Calculating variant.');
        $scrapedData->setVariant($this->variantGenerator->getId($configs->first()->getAttribute('type')));
        Log::info('Variant calculated.');

        return $scrapedData;
    }

    private function getCrawler(string $url): ?Crawler
    {
        try {
            Log::info("Requesting $url");

            return $this->client->request('GET', $url);
        } catch (TransportException $e) {
            Log::info("Unavailable url '$url'", ['message' => $e->getMessage()]);
            throw new UnexpectedValueException("Unavailable url '$url'");
        } catch (HttpExceptionInterface $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            Log::info('Invalid response http status', ['status' => $httpCode]);
            throw new UnexpectedValueException("Response error from '$url' with '$httpCode' http code");
        }
    }

    private function extractValue(Configuration $config, ?Crawler $crawler): ?array
    {
        foreach ($config->getAttribute('xpaths') as $xpath) {
            Log::debug("Checking xpath $xpath");
            $subcrawler = $crawler->evaluate($xpath);

            if ($subcrawler->count()) {
                Log::debug("Found xpath $xpath");
                $this->variantGenerator->addConfig($config->getAttribute('name'), $xpath);
                return $subcrawler->each(fn ($node) => $node->text());
            }
        }

        return null;
    }
}

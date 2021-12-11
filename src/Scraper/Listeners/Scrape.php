<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Joskfg\LaravelIntelligentScraper\Scraper\Application\XpathFinder;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Joskfg\LaravelIntelligentScraper\Scraper\Exceptions\MissingXpathValueException;
use Joskfg\LaravelIntelligentScraper\Scraper\Repositories\Configuration;
use Psr\Log\LoggerInterface;

class Scrape implements ShouldQueue
{
    private Configuration $configuration;

    private LoggerInterface $logger;

    private XpathFinder $xpathFinder;

    public function __construct(
        Configuration $configuration,
        XpathFinder $xpathFinder,
        LoggerInterface $logger
    ) {
        $this->configuration = $configuration;
        $this->xpathFinder   = $xpathFinder;
        $this->logger        = $logger;
    }

    public function handle(ScrapeRequest $scrapeRequest): void
    {
        try {
            $config = $this->loadConfiguration($scrapeRequest);
            $this->extractData($scrapeRequest, $config);
        } catch (MissingXpathValueException $e) {
            $this->logger->notice(
                "Invalid Configuration for '$scrapeRequest->url' and type '$scrapeRequest->type', error: {$e->getMessage()}."
            );

            event(new InvalidConfiguration($scrapeRequest));
        }
    }

    private function loadConfiguration(ScrapeRequest $scrapeRequest): Collection
    {
        $this->logger->info("Loading scrapping configuration for type '$scrapeRequest->type'");

        $config = $this->configuration->findByType($scrapeRequest->type);
        if ($config->isEmpty()) {
            throw new MissingXpathValueException('Missing initial configuration');
        }

        return $config;
    }

    private function extractData(ScrapeRequest $scrapeRequest, Collection $config): void
    {
        $this->logger->info("Extracting data from $scrapeRequest->url for type '$scrapeRequest->type'");

        $scrapedData = $this->xpathFinder->extract($scrapeRequest->url, $config);
        event(new Scraped($scrapeRequest, $scrapedData));
    }
}

<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use JsonException;
use Psr\Log\LoggerInterface;
use Softonic\LaravelIntelligentScraper\Scraper\Application\XpathFinder;
use Softonic\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Softonic\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeFailed;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Softonic\LaravelIntelligentScraper\Scraper\Exceptions\ConfigurationException;
use Softonic\LaravelIntelligentScraper\Scraper\Exceptions\MissingXpathValueException;
use Softonic\LaravelIntelligentScraper\Scraper\Repositories\Configuration;
use UnexpectedValueException;

class ConfigureScraper implements ShouldQueue
{
    /**
     * Specific queue for configure scrapper.
     *
     * @var string
     */
    public string $queue = 'configure';

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var XpathFinder
     */
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

    /**
     * @throws JsonException
     */
    public function handle(InvalidConfiguration $invalidConfiguration): void
    {
        try {
            $scrapeRequest = $invalidConfiguration->scrapeRequest;
            $config        = $this->configuration->calculate($scrapeRequest->type);
            $this->extractData($scrapeRequest, $config);
            $config->map->save();
        } catch (MissingXpathValueException $e) {
            $this->logger->notice(
                "Configuration not available for '$scrapeRequest->url' and type '$scrapeRequest->type', error: {$e->getMessage()}."
            );
            event(new ScrapeFailed($invalidConfiguration->scrapeRequest));
        } catch (UnexpectedValueException | ConfigurationException $e) {
            $this->scrapeFailed($invalidConfiguration, $scrapeRequest, $e);
        }
    }

    /**
     * @param ScrapeRequest $scrapeRequest
     * @param               $config
     */
    private function extractData(ScrapeRequest $scrapeRequest, $config): void
    {
        $this->logger->info("Extracting data from $scrapeRequest->url for type '$scrapeRequest->type'");

        ['data' => $data, 'variant' => $variant] = $this->xpathFinder->extract($scrapeRequest->url, $config);
        event(new Scraped($scrapeRequest, $data, $variant));
    }

    /**
     * @param InvalidConfiguration $invalidConfiguration
     * @param                      $scrapeRequest
     * @param                      $e
     */
    private function scrapeFailed(InvalidConfiguration $invalidConfiguration, $scrapeRequest, $e): void
    {
        $this->logger->error(
            "Error scraping '$scrapeRequest->url'",
            ['message' => $e->getMessage()]
        );
        event(new ScrapeFailed($invalidConfiguration->scrapeRequest));
    }
}

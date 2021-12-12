<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeFailed;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\Configuration;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use Tests\TestCase;

/**
 * THIS TEST SHOULD BE CONFIGURED FIRST. NOT WORKING AS IT IS.
 * FILL ALL PROVIDERS BEFORE RUNNING THE TEST.
 */
class CrawlingTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Configure this provider to check if the crawler is working as expected.
     */
    public function fieldCrawlProvider(): array
    {
        return [
            [
                // Url to be crawled
                'urlToCrawl'    => '',
                // Xpath where to find the expected value
                'xpath'         => '',
                // Remember it must be a list of values.
                'expectedValue' => [],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider fieldCrawlProvider
     * @param mixed $urlToCrawl
     * @param mixed $xpath
     * @param mixed $expectedValue
     */
    public function crawlPageExtractingAField($urlToCrawl, $xpath, $expectedValue): void
    {
        $type      = 'type-example';
        $fieldName = 'semantic-field-name';

        Configuration::create([
            'name'   => $fieldName,
            'type'   => $type,
            'xpaths' => $xpath,
        ]);

        Event::listen(
            Scraped::class,
            fn (Scraped $scraped) => self::assertSame(
                $expectedValue,
                $scraped->scrapedData->getField($fieldName)->getValue()
            )
        );
        Event::listen(ScrapeFailed::class, fn () => self::fail('Scrape failed'));

        scrape($urlToCrawl, $type);
    }

    public function getDatasetToReconfigureProvider(): array
    {
        return [
            [
                'dataset'            => [
                    // Url to be crawled from a past crawl or defined dataset
                    'url'   => '',
                    'field' => [
                        // Field name
                        'key'   => '',
                        // Value retrieved in list format
                        'value' => [],
                    ],
                ],
                // Url to be crawled where we want to get the data
                'urlToCrawl'         => '',
                // Value that we are expecting to obtain in list format
                'expectedFieldValue' => [],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getDatasetToReconfigureProvider
     * @param mixed $dataset
     * @param mixed $urlToCrawl
     * @param mixed $expectedFieldValue
     */
    public function configureAutomaticallyCrawlerAndCrawlAField($dataset, $urlToCrawl, $expectedFieldValue): void
    {
        $dataset['field']['found'] = true;
        $fieldName                 = $dataset['field']['key'];
        $type                      = 'type-example';

        ScrapedDataset::create([
            'url_hash' => hash('sha256', $dataset['url']),
            'url'      => $dataset['url'],
            'type'     => $type,
            'variant'  => Str::random(),
            'fields'   => [$dataset['field']],
        ]);

        Event::listen(
            InvalidConfiguration::class,
            fn () => self::assertTrue(
                true,
                'This event must be dispatched to reconfigure the crawler'
            )
        );
        Event::listen(
            ScrapeFailed::class,
            fn () => self::assertTrue(
                true,
                'The scrape fails due to misconfiguration'
            )
        );
        Event::listen(
            Scraped::class,
            fn (Scraped $scraped) => self::assertSame(
                $expectedFieldValue,
                $scraped->scrapedData->getField($fieldName)->getValue()
            )
        );
        scrape($urlToCrawl, $type);

        Event::forget(InvalidConfiguration::class);
        Event::listen(
            InvalidConfiguration::class,
            fn () => self::fail('It should be already reconfigured')
        );
        Event::forget(ScrapeFailed::class);
        Event::listen(
            ScrapeFailed::class,
            fn () => self::fail('The scrape should not fail this time')
        );

        Event::listen(
            Scraped::class,
            fn (Scraped $scraped) => self::assertSame(
                $expectedFieldValue,
                $scraped->scrapedData->getField($fieldName)->getValue()
            )
        );

        scrape($urlToCrawl, $type);
    }

    /**
     * @test
     * @dataProvider getDatasetToReconfigureProvider
     * @param mixed $dataset
     * @param mixed $urlToCrawl
     * @param mixed $expectedFieldValue
     */
    public function configureAutomaticallyCrawlerWithoutDatasetFoundInfoIsNotPossible($dataset, $urlToCrawl, $expectedFieldValue): void
    {
        $dataset['field']['found'] = false;
        $fieldName                 = $dataset['field']['key'];
        $type                      = 'type-example';

        ScrapedDataset::create([
            'url_hash' => hash('sha256', $dataset['url']),
            'url'      => $dataset['url'],
            'type'     => $type,
            'variant'  => Str::random(),
            'fields'   => [$dataset['field']],
        ]);

        Event::listen(
            InvalidConfiguration::class,
            fn () => self::assertTrue(
                true,
                'This event must be dispatched to reconfigure the crawler'
            )
        );
        Event::listen(
            ScrapeFailed::class,
            fn () => self::assertTrue(
                true,
                'The scrape fails due to misconfiguration'
            )
        );
        Event::listen(
            Scraped::class,
            fn (Scraped $scraped) => self::fail('It should not try to scrape the url without reconfiguration')
        );

        scrape($urlToCrawl, $type);
    }

    public function getChainedTypesConfigurationProvider(): array
    {
        return [
            [
                // Url to be crawled
                'urlToCrawl'    => '',
                // Xpath where to find the next URL to crawl
                'urlXpath'      => '',
                // Final Xpath to crawl in the chained crawl
                'finalXpath'    => '',
                // Final value to be found in the final Xpath in list format
                'value' => [],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getChainedTypesConfigurationProvider
     * @param mixed $urlToCrawl
     * @param mixed $urlXpath
     * @param mixed $finalXpath
     * @param mixed $expectedValue
     */
    public function whenCrawlingFieldsWithChainedTypesItShouldContinueCrawlingTheChainedTypes(
        $urlToCrawl,
        $urlXpath,
        $finalXpath,
        $expectedValue
    ): void {
        $type      = 'type-example';
        $fieldName = 'semantic-field-name';
        $childType = 'child-type-example';
        $childFieldName = 'child-semantic-field-name';

        Configuration::create([
            'name'   => $fieldName,
            'type'   => $type,
            'xpaths' => $urlXpath,
            'chain_type' => $childType,
        ]);

        Configuration::create([
            'name'   => $childFieldName,
            'type'   => $childType,
            'xpaths' => $finalXpath,
        ]);

        Event::listen(
            Scraped::class,
            function (Scraped $scraped) use ($expectedValue, $childType, $childFieldName) {
                if ($scraped->scrapeRequest->type === $childType) {
                    self::assertSame(
                        $expectedValue,
                        $scraped->scrapedData->getField($childFieldName)
                            ->getValue()
                    );
                }
            }
        );
        Event::listen(ScrapeFailed::class, fn () => self::fail('Scrape failed'));

        scrape($urlToCrawl, $type);
    }
}

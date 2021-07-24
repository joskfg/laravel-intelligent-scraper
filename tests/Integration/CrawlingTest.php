<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Softonic\LaravelIntelligentScraper\Scraper\Events\InvalidConfiguration;
use Softonic\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Softonic\LaravelIntelligentScraper\Scraper\Events\ScrapeFailed;
use Softonic\LaravelIntelligentScraper\Scraper\Models\Configuration;
use Softonic\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use Tests\TestCase;

class CrawlingTest extends TestCase
{
    use DatabaseMigrations;

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
            'url'     => $dataset['url'],
            'type'    => $type,
            'variant' => Str::random(),
            'fields'  => [$dataset['field']],
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
            'url'     => $dataset['url'],
            'type'    => $type,
            'variant' => Str::random(),
            'fields'  => [$dataset['field']],
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
}

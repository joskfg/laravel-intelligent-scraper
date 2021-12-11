<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Listeners;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\Field;
use Joskfg\LaravelIntelligentScraper\Scraper\Entities\ScrapedData;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\Scraped;
use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;
use ScrapedDatasetSeeder;
use Tests\TestCase;

class UpdateDatasetTest extends TestCase
{
    use DatabaseMigrations;

    private UpdateDataset $updateDataset;

    public function setUp(): void
    {
        parent::setUp();

        Log::spy();

        $this->updateDataset = new UpdateDataset();
    }

    /**
     * @test
     */
    public function whenDatasetExistsItShouldBeUpdated(): void
    {
        $seeder  = new ScrapedDatasetSeeder();
        $dataset = $seeder->createScrapedDatasets(2)->first();

        $scrapedData = new ScrapedData(
            ':variant:',
            [
                new Field(':field-1:', [':value-1:']),
                new Field(':field-2:', [':value-2:']),
                new Field(':field-3:', [':value-3:']),
            ]
        );

        $this->updateDataset->handle(
            new Scraped(
                new ScrapeRequest($dataset->url, ':type:'),
                $scrapedData
            )
        );

        self::assertEquals(
            json_encode($scrapedData->getFields()),
            json_encode(ScrapedDataset::where('url', $dataset->url)->first()->toArray()['fields'])
        );
        self::assertEquals(2, ScrapedDataset::all()->count());
    }

    /**
     * @test
     */
    public function whenDatasetDoesNotExistAndTheDatasetsLimitHasNotBeenReachedItShouldBeSaved(): void
    {
        factory(ScrapedDataset::class, UpdateDataset::DATASET_AMOUNT_LIMIT - 1)->create([
            'variant' => ':variant-1:',
        ]);
        factory(ScrapedDataset::class)->create([
            'variant' => ':variant-2:',
        ]);

        $url  = ':scrape-url:';

        $scrapedData = new ScrapedData(
            ':variant-1:',
            [
                new Field(':field-1:', [':value-1:']),
                new Field(':field-2:', [':value-2:']),
                new Field(':field-3:', [':value-3:']),
            ]
        );

        $this->updateDataset->handle(
            new Scraped(
                new ScrapeRequest($url, ':type:'),
                $scrapedData
            )
        );

        self::assertEquals(
            json_encode($scrapedData->getFields()),
            json_encode(ScrapedDataset::where('url', $url)->first()->toArray()['fields'])
        );
        self::assertEquals(101, ScrapedDataset::count());
    }

    /**
     * @test
     */
    public function whenDatasetDoesNotExistAndTheDatasetsLimitHasReachedItShouldDeleteTheExcess(): void
    {
        $type = ':type:';
        factory(ScrapedDataset::class, UpdateDataset::DATASET_AMOUNT_LIMIT + 10)->create([
            'type'    => $type,
            'variant' => ':variant:',
        ]);

        $url  = ':scrape-url:';

        $scrapedData = new ScrapedData(
            ':variant:',
            [
                new Field(':field-1:', [':value-1:']),
                new Field(':field-2:', [':value-2:']),
            ]
        );

        $this->updateDataset->handle(
            new Scraped(
                new ScrapeRequest($url, $type),
                $scrapedData
            )
        );

        self::assertEquals(
            json_encode($scrapedData->getFields()),
            json_encode(ScrapedDataset::where('url', $url)->first()->toArray()['fields'])
        );
        self::assertEquals(UpdateDataset::DATASET_AMOUNT_LIMIT, ScrapedDataset::withType($type)->count());
    }
}

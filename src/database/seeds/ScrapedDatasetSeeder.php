<?php /** @noinspection ALL */

use Illuminate\Database\Seeder;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;

class ScrapedDatasetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     */
    public function run()
    {
        ScrapedDataset::factory()->count(2)->create();
    }

    public function createScrapedDatasets(int $amount): \Illuminate\Support\Collection
    {
        return ScrapedDataset::factory()->count($amount)->create();
    }
}

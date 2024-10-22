<?php /** @noinspection ALL */

namespace Database\Factories\Joskfg\LaravelIntelligentScraper\Scraper\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;

class ScrapedDatasetFactory extends Factory
{
    protected $model = ScrapedDataset::class;

    public function definition()
    {
        $url = $this->faker->url . $this->faker->randomDigit;
        return [
            'url_hash' => hash('sha256', $url),
            'url'      => $url,
            'type'     => 'post',
            'variant'  => $this->faker->sha1,
            'fields'   => [
                [
                    'key'   => 'title',
                    'value' => $this->faker->word,
                    'found' => $this->faker->boolean(),
                ],
                [
                    'key'   => 'author',
                    'value' => $this->faker->word,
                    'found' => $this->faker->boolean(),
                ],
            ],
        ];
    }
}

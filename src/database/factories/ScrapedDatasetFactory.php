<?php /** @noinspection ALL */

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

use Joskfg\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(ScrapedDataset::class, function (Faker\Generator $faker) {
    $url = $faker->url . $faker->randomDigit;

    return [
        'url_hash' => hash('sha256', $url),
        'url'      => $url,
        'type'     => 'post',
        'variant'  => $faker->sha1,
        'fields'   => [
            [
                'key'   => 'title',
                'value' => $faker->word,
                'found' => $faker->boolean(),
            ],
            [
                'key'   => 'author',
                'value' => $faker->word,
                'found' => $faker->boolean(),
            ],
        ],
    ];
});

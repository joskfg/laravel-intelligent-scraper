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

use Softonic\LaravelIntelligentScraper\Scraper\Models\ScrapedDataset;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(ScrapedDataset::class, fn (Faker\Generator $faker) => [
    'url'     => $faker->url . $faker->randomDigit,
    'type'    => 'post',
    'variant' => $faker->sha1,
    'fields'  => [
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
]);

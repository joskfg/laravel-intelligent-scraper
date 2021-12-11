<?php

namespace Tests;

use Joskfg\LaravelIntelligentScraper\ScraperProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withFactories(__DIR__ . '/../src/database/factories');
    }

    protected function getPackageProviders($app): array
    {
        return [ScraperProvider::class];
    }
}

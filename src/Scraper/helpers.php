<?php

use Joskfg\LaravelIntelligentScraper\Scraper\Events\ScrapeRequest;

if (!function_exists('regexp')) {
    function regexp($regexp): array
    {
        return ['regexp' => $regexp];
    }
}

if (!function_exists('scrape')) {
    function scrape($url, $type, $context = [])
    {
        event(new ScrapeRequest($url, $type, $context));
    }
}

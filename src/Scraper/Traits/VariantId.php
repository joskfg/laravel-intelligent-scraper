<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Traits;

trait VariantId
{
    protected function getVariantId(string $type, array $variant): ?string
    {
        if (empty($variant)) {
            return null;
        }

        sort($variant);

        return sha1($type . implode('', $variant));
    }
}

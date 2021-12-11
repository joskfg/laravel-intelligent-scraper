<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Application;

use InvalidArgumentException;

class VariantGenerator
{
    protected ?string $type = null;

    protected array $configPerField = [];

    protected bool $allFieldsFound = true;

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function addConfig($field, $xpath): void
    {
        $this->configPerField[] = $field . $xpath;
    }

    public function fieldNotFound(): void
    {
        $this->allFieldsFound = false;
    }

    public function getId(?string $type = null): string
    {
        $type ??= $this->type;
        if (empty($type)) {
            throw new InvalidArgumentException('Type should be provided in the getVariantId call or setType');
        }

        if (empty($this->configPerField) || !$this->allFieldsFound) {
            return '';
        }

        sort($this->configPerField);

        $id = sha1($type . implode('', $this->configPerField));
        $this->reset();

        return $id;
    }

    public function reset(): void
    {
        $this->setType(null);
        $this->configPerField = [];
        $this->allFieldsFound = true;
    }
}

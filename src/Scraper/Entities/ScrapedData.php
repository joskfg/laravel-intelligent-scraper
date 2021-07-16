<?php


namespace Softonic\LaravelIntelligentScraper\Scraper\Entities;


class ScrapedData
{
    private ?string $variant;
    private array $fields;

    public function __construct(?string $variant = null, array $fields = [])
    {
        $this->variant = $variant;
        $this->fields  = $fields;
    }

    public function getVariant(): ?string
    {
        return $this->variant;
    }

    public function setVariant(?string $variant): ScrapedData
    {
        $this->variant = $variant;

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): ScrapedData
    {
        $this->fields = $fields;

        return $this;
    }

    public function getField(string $key): array
    {
        return $this->fields[$key];
    }

    public function setField(string $key, $value): ScrapedData
    {
        $this->fields[$key] = $value;

        return $this;
    }
}
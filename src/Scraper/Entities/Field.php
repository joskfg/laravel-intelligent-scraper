<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Entities;

class Field implements \JsonSerializable
{
    private string $key;
    private $value;
    private bool $found;

    public function __construct(string $key, $value, bool $found = true)
    {
        $this->key   = $key;
        $this->value = $value;
        $this->found = $found;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): Field
    {
        $this->key = $key;

        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    public function isFound(): bool
    {
        return $this->found;
    }

    public function setFound(bool $found): Field
    {
        $this->found = $found;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'key'   => $this->getKey(),
            'value' => $this->getValue(),
            'foind' => $this->isFound(),
        ];
    }
}

<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Entities;

class Field implements \JsonSerializable
{
    private string $key;
    private $value;
    private ?string $chainType;
    private bool $found;

    public function __construct(string $key, $value, ?string $chainType = null, bool $found = true)
    {
        $this->key       = $key;
        $this->value     = $value;
        $this->found     = $found;
        $this->chainType = $chainType;
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

    public function getChainType(): ?string
    {
        return $this->chainType;
    }

    public function setChainType(string $chainType): Field
    {
        $this->chainType = $chainType;

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
            'key'        => $this->getKey(),
            'value'      => $this->getValue(),
            'chain_type' => $this->getChainType(),
            'found'      => $this->isFound(),
        ];
    }
}

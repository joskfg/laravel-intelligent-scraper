<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Entities;

class ScrapedData
{
    private ?string $variant;
    private array $fields;

    public function __construct(?string $variant = null, array $fields = [])
    {
        $this->variant = $variant;
        $this->fields  = $fields;
    }

    private function checkFields(array $fields): void
    {
        foreach ($fields as $field) {
            if (!$field instanceof Field) {
                throw new \InvalidArgumentException('Fields received are not Field entities');
            }
        }
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

    /**
     * @return array<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): ScrapedData
    {
        $this->checkFields($fields);
        $this->fields = $fields;

        return $this;
    }

    public function getField(string $key): Field
    {
        return $this->fields[$key];
    }

    public function setField(Field $field): ScrapedData
    {
        $this->fields[$field->getKey()] = $field;

        return $this;
    }
}

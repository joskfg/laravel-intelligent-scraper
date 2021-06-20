<?php

namespace Softonic\LaravelIntelligentScraper\Scraper\Application;

use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class XpathBuilder
{
    private string $idsToIgnore;

    public function __construct(string $idsToIgnore)
    {
        $this->idsToIgnore = $idsToIgnore;
    }

    /**
     * Indicates if the comparison should be done using a regexp.
     */
    private const REGEXP_COMPARISON = 'regexp';

    public function find($documentElement, $values): string
    {
        $values = (!is_array($values) || array_key_exists(self::REGEXP_COMPARISON, $values))
            ? [$values]
            : $values;

        Log::debug('Trying to find a xpath for the given values', compact('values'));

        $nodes = [];
        foreach ($values as $value) {
            $nodes[] = $this->findNode($documentElement, $value);
        }

        return $this->getXPath($nodes);
    }

    public function findNode($documentElement, $value)
    {
        [$value, $isFoundCallback] = $this->getComparisonCallbackWithBasicValue($value);

        $node = $this->getNodeWithValue([$documentElement], $isFoundCallback);
        if (empty($node)) {
            throw new UnexpectedValueException("'$value' not found");
        }

        return $node;
    }

    /**
     * Get the comparison callback with the value used internally.
     *
     * The callback will be different depending on the value. If the value
     * contains a regexp the callback will evaluate it, if not, the value is a simple value
     * and it is checked as a normal equal.
     *
     * @param mixed $value
     */
    private function getComparisonCallbackWithBasicValue($value): array
    {
        if (is_array($value) && array_key_exists(self::REGEXP_COMPARISON, $value)) {
            $value           = $value[self::REGEXP_COMPARISON];
            $isFoundCallback = $this->regexpComparison($value);

            return [$value, $isFoundCallback];
        }

        $isFoundCallback = $this->normalComparison($value);

        return [$value, $isFoundCallback];
    }

    private function regexpComparison($regexp): callable
    {
        return static fn ($string): bool => (bool)preg_match($regexp, $string);
    }

    private function normalComparison($value): callable
    {
        return static fn ($string): bool => $string === $value;
    }

    private function getNodeWithValue($nodes, $isFoundCallback)
    {
        foreach ($nodes as $item) {
            if ($isFoundCallback($item->textContent)) {
                return $item;
            }

            foreach ($item->attributes ?? [] as $attribute) {
                if ($isFoundCallback($attribute->value)) {
                    return $attribute;
                }
            }

            if ($item->hasChildNodes()) {
                $result = $this->getNodeWithValue($item->childNodes, $isFoundCallback);
                if (!empty($result)) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function getXPath(array $nodes): string
    {
        Log::debug('Calculating xpath for the given nodes.');
        $elements = [];
        foreach ($nodes as $node) {
            $elements[] = $this->optimizeElements($node, $this->getPathElements($node));
        }

        Log::debug('Getting common elements between xpaths.');
        $finalElements = (count($elements) > 1) ? $this->getCommonElements($elements) : $elements[0];

        Log::debug('Getting common elements between xpaths.');
        $finalXpath = implode('/', array_reverse($finalElements));
        Log::debug("Xpath generated: $finalXpath.");

        return $finalXpath;
    }

    private function optimizeElements($node, $elements, $childNode = null, $index = 0)
    {
        if ('meta' === $node->nodeName) {
            foreach ($node->attributes as $attribute) {
                if ('name' === $attribute->name) {
                    return ["//meta[@name=\"$attribute->value\"]/@$childNode->nodeName"];
                }
            }
        }

        foreach ($node->attributes ?? [] as $attribute) {
            if ($attribute->name === 'id' && !preg_match($this->idsToIgnore, $attribute->value)) {
                $elements[$index] = "//*[@id=\"$attribute->value\"]";

                return array_slice($elements, 0, $index + 1);
            }
        }

        if (empty($node->parentNode)) {
            return $elements;
        }

        return $this->optimizeElements($node->parentNode, $elements, $node, $index + 1);
    }

    private function getPathElements($node)
    {
        $nodePath = $node->getNodePath();
        $parts    = explode('/', $nodePath);

        return array_reverse($parts);
    }

    private function getCommonElements($elements): array
    {
        $fixedElements = array_intersect_assoc(...$elements);
        $finalElements = [];
        $totalElements = count($elements[0]);

        for ($i = 0; $i < $totalElements; ++$i) {
            $finalElements[$i] = $fixedElements[$i] ?? '*';
        }

        return $finalElements;
    }
}

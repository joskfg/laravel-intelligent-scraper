<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\Configuration\Option;
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->import(SetList::DEAD_CODE);
    $rectorConfig->import(SetList::TYPE_DECLARATION);
    $rectorConfig->import(SetList::EARLY_RETURN);
    $rectorConfig->import(SetList::PRIVATIZATION);
    $rectorConfig->import(SetList::PHP_83);
    $rectorConfig->skip([
        __DIR__ . '/vendor',
    ]);
    $rectorConfig->autoloadPaths([__DIR__ . '/vendor/autoload.php']);
    $rectorConfig->importNames();
};



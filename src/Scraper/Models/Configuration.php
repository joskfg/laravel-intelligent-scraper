<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Models;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    public $casts = [
        'xpaths'   => 'json',
        'optional' => 'bool',
    ];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'name';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'chain_type',
        'xpaths',
        'optional',
        'default',
    ];

    public function getXpathsAttribute($xpaths): array
    {
        return (array)$this->castAttribute('xpaths', $xpaths);
    }

    public function scopeWithType($query, string $type)
    {
        return $query->where('type', $type);
    }
}

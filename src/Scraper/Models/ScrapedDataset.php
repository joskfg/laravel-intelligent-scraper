<?php

namespace Joskfg\LaravelIntelligentScraper\Scraper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScrapedDataset extends Model
{
    use HasFactory;
    
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
    public $casts = ['fields' => 'json'];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'url_hash';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url_hash',
        'url',
        'type',
        'variant',
        'fields',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->url_hash = hash('sha256', $model->url);
        });
    }

    public function scopeWithType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithVariant($query, string $variant)
    {
        return $query->where('variant', $variant);
    }
}

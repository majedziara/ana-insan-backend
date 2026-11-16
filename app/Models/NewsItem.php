<?php

namespace App\Models;

use Database\Factories\NewsItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsItem extends Model
{
    /** @use HasFactory<NewsItemFactory> */
    use HasFactory;

    protected $fillable = ['text', 'text_en', 'duration_days', 'is_active', 'published_at', 'expires_at'];

    protected function casts(): array
    {
        return ['duration_days' => 'integer', 'is_active' => 'boolean', 'published_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('published_at', '<=', now())->where('expires_at', '>', now());
    }
}

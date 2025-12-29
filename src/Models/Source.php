<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $table = 'sources';
    
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'url',
        'last_refresh',
    ];
    
    protected $casts = [
        'last_refresh' => 'datetime',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    
    public function feedItems(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }
    
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(FeedList::class, 'list_sources', 'source_id', 'list_id')
            ->withPivot(['author_whitelist', 'author_blacklist', 'category_whitelist', 'category_blacklist'])
            ->withTimestamps();
    }
    
    public function isGlobal(): bool
    {
        return $this->category->isGlobal();
    }
}


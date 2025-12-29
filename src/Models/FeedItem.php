<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedItem extends Model
{
    protected $table = 'feed_items';
    
    public $timestamps = false;
    
    protected $fillable = [
        'source_id',
        'guid',
        'title',
        'link',
        'content',
        'author',
        'categories',
        'image_url',
        'pub_date',
    ];
    
    protected $casts = [
        'pub_date' => 'datetime',
        'created_at' => 'datetime',
    ];
    
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
    
    public function getCategoriesArray(): array
    {
        if (empty($this->categories)) {
            return [];
        }
        return array_map('trim', explode(',', $this->categories));
    }
}


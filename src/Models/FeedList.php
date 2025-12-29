<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FeedList extends Model
{
    protected $table = 'lists';
    
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'is_public',
    ];
    
    protected $casts = [
        'is_public' => 'boolean',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'list_sources', 'list_id', 'source_id')
            ->withPivot(['author_whitelist', 'author_blacklist', 'category_whitelist', 'category_blacklist'])
            ->withTimestamps();
    }
    
    public static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}


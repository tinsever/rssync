<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'categories';
    
    protected $fillable = [
        'name',
        'user_id',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }
    
    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }
    
    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id');
    }
    
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $userId);
        });
    }
}


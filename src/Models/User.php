<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $table = 'users';
    
    protected $fillable = [
        'email',
        'workos_user_id',
        'email_verified_at',
        'verification_token',
        'reset_token',
        'reset_token_expires',
    ];
    
    protected $hidden = [
        'password',
        'verification_token',
        'reset_token',
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'reset_token_expires' => 'datetime',
    ];
    
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
    
    public function lists(): HasMany
    {
        return $this->hasMany(FeedList::class);
    }
    
    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Find user by WorkOS user ID
     */
    public static function findByWorkOSId(string $workosUserId): ?self
    {
        return self::where('workos_user_id', $workosUserId)->first();
    }
}


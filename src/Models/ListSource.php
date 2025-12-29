<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListSource extends Model
{
    protected $table = 'list_sources';
    
    protected $fillable = [
        'list_id',
        'source_id',
        'author_whitelist',
        'author_blacklist',
        'category_whitelist',
        'category_blacklist',
    ];
    
    public function list(): BelongsTo
    {
        return $this->belongsTo(FeedList::class, 'list_id');
    }
    
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
    
    public function getAuthorWhitelistArray(): array
    {
        if (empty($this->author_whitelist)) {
            return [];
        }
        return array_map('trim', explode(',', $this->author_whitelist));
    }
    
    public function getAuthorBlacklistArray(): array
    {
        if (empty($this->author_blacklist)) {
            return [];
        }
        return array_map('trim', explode(',', $this->author_blacklist));
    }
    
    public function getCategoryWhitelistArray(): array
    {
        if (empty($this->category_whitelist)) {
            return [];
        }
        return array_map('trim', explode(',', $this->category_whitelist));
    }
    
    public function getCategoryBlacklistArray(): array
    {
        if (empty($this->category_blacklist)) {
            return [];
        }
        return array_map('trim', explode(',', $this->category_blacklist));
    }
    
    public function filterItem(FeedItem $item): bool
    {
        // Check author whitelist
        $authorWhitelist = $this->getAuthorWhitelistArray();
        if (!empty($authorWhitelist)) {
            $authorMatch = false;
            foreach ($authorWhitelist as $author) {
                if (stripos($item->author ?? '', $author) !== false) {
                    $authorMatch = true;
                    break;
                }
            }
            if (!$authorMatch) {
                return false;
            }
        }
        
        // Check author blacklist
        $authorBlacklist = $this->getAuthorBlacklistArray();
        foreach ($authorBlacklist as $author) {
            if (stripos($item->author ?? '', $author) !== false) {
                return false;
            }
        }
        
        // Check category whitelist
        $categoryWhitelist = $this->getCategoryWhitelistArray();
        if (!empty($categoryWhitelist)) {
            $itemCategories = $item->getCategoriesArray();
            $categoryMatch = false;
            foreach ($categoryWhitelist as $cat) {
                foreach ($itemCategories as $itemCat) {
                    if (stripos($itemCat, $cat) !== false) {
                        $categoryMatch = true;
                        break 2;
                    }
                }
            }
            if (!$categoryMatch) {
                return false;
            }
        }
        
        // Check category blacklist
        $categoryBlacklist = $this->getCategoryBlacklistArray();
        $itemCategories = $item->getCategoriesArray();
        foreach ($categoryBlacklist as $cat) {
            foreach ($itemCategories as $itemCat) {
                if (stripos($itemCat, $cat) !== false) {
                    return false;
                }
            }
        }
        
        return true;
    }
}


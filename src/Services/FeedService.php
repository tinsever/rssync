<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FeedItem;
use App\Models\ListSource;
use App\Models\Source;
use Laminas\Feed\Reader\Reader;

class FeedService
{
    public function validateFeed(string $url): bool
    {
        try {
            $content = $this->fetchUrl($url);
            if (!$content) {
                return false;
            }
            $feed = Reader::importString($content);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function refreshSource(Source $source): int
    {
        try {
            $content = $this->fetchUrl($source->url);
            if (!$content) {
                return -1;
            }
            
            $feed = Reader::importString($content);
            $newItems = 0;
            
            foreach ($feed as $entry) {
                $guid = $entry->getId() ?: md5($entry->getLink() . $entry->getTitle());
                
                // Check if item already exists
                if (FeedItem::where('source_id', $source->id)->where('guid', $guid)->exists()) {
                    continue;
                }
                
                // Get categories
                $categories = [];
                try {
                    $entryCategories = $entry->getCategories();
                    if ($entryCategories) {
                        foreach ($entryCategories as $category) {
                            if (is_object($category)) {
                                $categories[] = $category->getLabel() ?: $category->getTerm();
                            } elseif (is_array($category)) {
                                $categories[] = $category['label'] ?? $category['term'] ?? '';
                            } elseif (is_string($category)) {
                                $categories[] = $category;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore category parsing errors
                }
                
                // Get author
                $author = null;
                try {
                    $entryAuthors = $entry->getAuthors();
                    if ($entryAuthors) {
                        $authors = [];
                        foreach ($entryAuthors as $a) {
                            if (is_object($a)) {
                                $authors[] = $a->getName() ?: $a->getEmail();
                            } elseif (is_array($a)) {
                                $authors[] = $a['name'] ?? $a['email'] ?? '';
                            } elseif (is_string($a)) {
                                $authors[] = $a;
                            }
                        }
                        $author = implode(', ', array_filter($authors));
                    }
                } catch (\Exception $e) {
                    // Ignore author parsing errors
                }
                
                // Get publication date
                $pubDate = $entry->getDateModified() ?? $entry->getDateCreated() ?? new \DateTime();
                
                // Get content
                $content = $entry->getContent() ?: $entry->getDescription();
                
                // Get image URL from enclosure or content
                $imageUrl = $this->extractImageUrl($entry, $content);
                
                // Create feed item
                $item = new FeedItem();
                $item->source_id = $source->id;
                $item->guid = substr($guid, 0, 500);
                $item->title = substr($entry->getTitle() ?? 'Ohne Titel', 0, 500);
                $item->link = substr($entry->getLink() ?? '', 0, 500);
                $item->content = $content;
                $item->author = $author ? substr($author, 0, 255) : null;
                $item->categories = !empty($categories) ? substr(implode(',', $categories), 0, 500) : null;
                $item->image_url = $imageUrl ? substr($imageUrl, 0, 1000) : null;
                $item->pub_date = $pubDate;
                $item->save();
                
                $newItems++;
            }
            
            // Update last refresh time
            $source->last_refresh = new \DateTime();
            $source->save();
            
            return $newItems;
        } catch (\Exception $e) {
            error_log('Feed refresh error for source ' . $source->id . ': ' . $e->getMessage());
            return -1;
        }
    }
    
    public function refreshAll(): array
    {
        $sources = Source::all();
        $results = [
            'total' => $sources->count(),
            'success' => 0,
            'failed' => 0,
            'new_items' => 0,
        ];
        
        foreach ($sources as $source) {
            $newItems = $this->refreshSource($source);
            
            if ($newItems >= 0) {
                $results['success']++;
                $results['new_items'] += $newItems;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    public function getFilteredItems(ListSource $listSource, int $limit = 50): array
    {
        $items = FeedItem::where('source_id', $listSource->source_id)
            ->orderBy('pub_date', 'desc')
            ->limit($limit * 2) // Get more to account for filtering
            ->get();
        
        $filteredItems = [];
        
        foreach ($items as $item) {
            if ($listSource->filterItem($item)) {
                $filteredItems[] = $item;
                
                if (count($filteredItems) >= $limit) {
                    break;
                }
            }
        }
        
        return $filteredItems;
    }
    
    public function getListItems(int $listId, int $limit = 100): array
    {
        $listSources = ListSource::where('list_id', $listId)->with('source')->get();
        $allItems = [];
        
        foreach ($listSources as $listSource) {
            $items = $this->getFilteredItems($listSource, $limit);
            foreach ($items as $item) {
                $allItems[] = $item;
            }
        }
        
        // Sort by publication date
        usort($allItems, function ($a, $b) {
            return $b->pub_date <=> $a->pub_date;
        });
        
        return array_slice($allItems, 0, $limit);
    }
    
    /**
     * Get unique authors from a source's feed items
     */
    public function getSourceAuthors(int $sourceId): array
    {
        $authors = FeedItem::where('source_id', $sourceId)
            ->whereNotNull('author')
            ->where('author', '!=', '')
            ->distinct()
            ->pluck('author')
            ->toArray();
        
        // Flatten comma-separated authors
        $allAuthors = [];
        foreach ($authors as $author) {
            $parts = array_map('trim', explode(',', $author));
            $allAuthors = array_merge($allAuthors, $parts);
        }
        
        return array_unique(array_filter($allAuthors));
    }
    
    /**
     * Get unique categories from a source's feed items
     */
    public function getSourceCategories(int $sourceId): array
    {
        $categories = FeedItem::where('source_id', $sourceId)
            ->whereNotNull('categories')
            ->where('categories', '!=', '')
            ->distinct()
            ->pluck('categories')
            ->toArray();
        
        // Flatten comma-separated categories
        $allCategories = [];
        foreach ($categories as $category) {
            $parts = array_map('trim', explode(',', $category));
            $allCategories = array_merge($allCategories, $parts);
        }
        
        return array_unique(array_filter($allCategories));
    }
    
    private function fetchUrl(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'RSSync/1.0 RSS Reader',
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        return $content !== false ? $content : null;
    }
    
    /**
     * Extract image URL from RSS entry (enclosure, media:content, or content)
     */
    private function extractImageUrl($entry, ?string $content): ?string
    {
        // Try to get enclosure first (most reliable)
        try {
            $enclosure = $entry->getEnclosure();
            if ($enclosure && isset($enclosure->url)) {
                $type = $enclosure->type ?? '';
                if (empty($type) || str_starts_with($type, 'image/')) {
                    return $enclosure->url;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Try media:content or media:thumbnail from entry XML
        try {
            $dom = $entry->getDomDocument();
            if ($dom) {
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('media', 'http://search.yahoo.com/mrss/');
                
                // Try media:content with image type
                $mediaContent = $xpath->query('//media:content[@url]');
                if ($mediaContent->length > 0) {
                    for ($i = 0; $i < $mediaContent->length; $i++) {
                        $node = $mediaContent->item($i);
                        $url = $node->getAttribute('url');
                        $medium = $node->getAttribute('medium');
                        $type = $node->getAttribute('type');
                        
                        // Prefer image types
                        if ($medium === 'image' || str_starts_with($type, 'image/') || empty($medium)) {
                            if ($url && $this->isValidImageUrl($url)) {
                                return $url;
                            }
                        }
                    }
                }
                
                // Try media:thumbnail
                $mediaThumbnail = $xpath->query('//media:thumbnail[@url]');
                if ($mediaThumbnail->length > 0) {
                    $url = $mediaThumbnail->item(0)->getAttribute('url');
                    if ($url && $this->isValidImageUrl($url)) {
                        return $url;
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Try to extract first meaningful image from content HTML
        if ($content) {
            $imageUrl = $this->extractFirstImageFromHtml($content);
            if ($imageUrl) {
                return $imageUrl;
            }
        }
        
        // Also try description
        try {
            $description = $entry->getDescription();
            if ($description) {
                $imageUrl = $this->extractFirstImageFromHtml($description);
                if ($imageUrl) {
                    return $imageUrl;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return null;
    }
    
    /**
     * Extract first meaningful image from HTML content
     */
    private function extractFirstImageFromHtml(string $html): ?string
    {
        // Find all img tags
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if ($this->isValidImageUrl($url)) {
                    return $url;
                }
            }
        }
        return null;
    }
    
    /**
     * Check if URL is a valid image (not a tracking pixel)
     */
    private function isValidImageUrl(string $url): bool
    {
        // Skip common tracking pixels and tiny images
        $skipPatterns = [
            '/1x1/', '/pixel/', '/tracking/', '/beacon/',
            '/spacer/', '/blank/', '/clear/', '/transparent/',
            'feedburner.com', 'feedsportal.com', 'statcounter.com',
            'google-analytics.com', 'doubleclick.net'
        ];
        
        $urlLower = strtolower($url);
        foreach ($skipPatterns as $pattern) {
            if (str_contains($urlLower, strtolower($pattern))) {
                return false;
            }
        }
        
        // Must be http/https
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return false;
        }
        
        return true;
    }
}

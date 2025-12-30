<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\FeedItem;
use App\Models\FeedList;
use App\Models\Source;
use App\Services\FeedService;
use Laminas\Feed\Reader\Reader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class HomeController
{
    public function __construct(
        private Twig $view,
        private FeedService $feedService
    ) {}
    
    public function index(Request $request, Response $response): Response
    {
        // Get recent feed items from all sources
        $recentItems = FeedItem::with('source.category')
            ->orderBy('pub_date', 'desc')
            ->limit(20)
            ->get();
        
        // Get public lists
        $publicLists = FeedList::where('is_public', true)
            ->withCount('sources')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();
        
        // Get categories with source count
        $categories = Category::whereNull('user_id')
            ->withCount('sources')
            ->orderBy('name')
            ->get();
        
        return $this->view->render($response, 'home/index.twig', [
            'recentItems' => $recentItems,
            'publicLists' => $publicLists,
            'categories' => $categories,
        ]);
    }
    
    public function sources(Request $request, Response $response): Response
    {
        // Get all categories that have sources (all sources are public)
        $categories = Category::whereHas('sources')
            ->with('sources')
            ->orderBy('name')
            ->get();
        
        $timezone = $_ENV['APP_TIMEZONE'] ?? 'Europe/Berlin';
        
        return $this->view->render($response, 'home/sources.twig', [
            'categories' => $categories,
            'timezone' => $timezone,
        ]);
    }
    
    public function viewSource(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        
        $source = Source::with('category')->find($id);
        
        if (!$source) {
            return $this->view->render($response->withStatus(404), 'errors/404.twig');
        }
        
        // Get authors from feed items
        $authors = $this->feedService->getSourceAuthors($source->id);
        
        // Get categories from feed items
        $feedCategories = $this->feedService->getSourceCategories($source->id);
        
        // Try to fetch feed description from the RSS feed
        $feedDescription = null;
        try {
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
            
            $content = @file_get_contents($source->url, false, $context);
            if ($content) {
                $feed = Reader::importString($content);
                $feedDescription = $feed->getDescription();
            }
        } catch (\Exception $e) {
            // If we can't fetch the feed, just continue without description
        }
        
        $timezone = $_ENV['APP_TIMEZONE'] ?? 'Europe/Berlin';
        
        return $this->view->render($response, 'home/source-view.twig', [
            'source' => $source,
            'authors' => $authors,
            'feedCategories' => $feedCategories,
            'feedDescription' => $feedDescription,
            'timezone' => $timezone,
        ]);
    }
    
    public function lists(Request $request, Response $response): Response
    {
        $lists = FeedList::where('is_public', true)
            ->with('user')
            ->withCount('sources')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $this->view->render($response, 'home/lists.twig', [
            'lists' => $lists,
        ]);
    }
    
    public function viewList(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        
        $list = FeedList::where('slug', $slug)->where('is_public', true)->first();
        
        if (!$list) {
            // Try to find list for logged-in user
            if (isset($_SESSION['user_id'])) {
                $list = FeedList::where('slug', $slug)
                    ->where('user_id', $_SESSION['user_id'])
                    ->first();
            }
            
            if (!$list) {
                return $this->view->render($response->withStatus(404), 'errors/404.twig');
            }
        }
        
        $items = $this->feedService->getListItems($list->id, 100);
        
        return $this->view->render($response, 'home/list-view.twig', [
            'list' => $list,
            'items' => $items,
        ]);
    }
    
    public function listRss(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        
        $list = FeedList::where('slug', $slug)->where('is_public', true)->first();
        
        if (!$list) {
            if (isset($_SESSION['user_id'])) {
                $list = FeedList::where('slug', $slug)
                    ->where('user_id', $_SESSION['user_id'])
                    ->first();
            }
            
            if (!$list) {
                $response->getBody()->write('List not found');
                return $response->withStatus(404);
            }
        }
        
        $items = $this->feedService->getListItems($list->id, 100);
        
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        
        // Build RSS XML with Media RSS namespace
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"></rss>');
        $channel = $xml->addChild('channel');
        $channel->addChild('title', htmlspecialchars($list->name));
        $channel->addChild('link', $baseUrl . '/list/' . $list->slug);
        $channel->addChild('description', 'RSS-Feed erstellt mit RSSync');
        $channel->addChild('lastBuildDate', date('r'));
        
        foreach ($items as $item) {
            $rssItem = $channel->addChild('item');
            $rssItem->addChild('title', htmlspecialchars($item->title));
            $rssItem->addChild('link', htmlspecialchars($item->link));
            $rssItem->addChild('guid', htmlspecialchars($item->guid));
            $rssItem->addChild('pubDate', $item->pub_date->format('r'));
            
            if ($item->author) {
                $rssItem->addChild('author', htmlspecialchars($item->author));
            }
            
            // Build description content with image
            $descriptionContent = '';
            if ($item->image_url) {
                $descriptionContent .= '<p><img src="' . htmlspecialchars($item->image_url) . '" alt="" style="max-width:100%;height:auto;" /></p>';
            }
            if ($item->content) {
                $descriptionContent .= $item->content;
            }
            
            if ($descriptionContent) {
                $description = $rssItem->addChild('description');
                $node = dom_import_simplexml($description);
                $no = $node->ownerDocument;
                $node->appendChild($no->createCDATASection($descriptionContent));
            }
            
            if ($item->categories) {
                foreach ($item->getCategoriesArray() as $cat) {
                    $rssItem->addChild('category', htmlspecialchars($cat));
                }
            }
            
            // Add image as media:content
            if ($item->image_url) {
                $mediaContent = $rssItem->addChild('content', null, 'http://search.yahoo.com/mrss/');
                $mediaContent->addAttribute('url', $item->image_url);
                $mediaContent->addAttribute('medium', 'image');
            }
            
            // Add source info
            $rssItem->addChild('source', htmlspecialchars($item->source->name))->addAttribute('url', $item->source->url);
        }
        
        $response->getBody()->write($xml->asXML());
        return $response
            ->withHeader('Content-Type', 'application/rss+xml; charset=utf-8')
            ->withStatus(200);
    }
}


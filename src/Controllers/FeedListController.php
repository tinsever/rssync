<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\FeedList;
use App\Models\ListSource;
use App\Models\Source;
use App\Services\FeedService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class FeedListController
{
    public function __construct(
        private Twig $view,
        private Messages $flash,
        private FeedService $feedService
    ) {}
    
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $lists = FeedList::where('user_id', $userId)
            ->withCount('sources')
            ->orderBy('name')
            ->get();
        
        return $this->view->render($response, 'dashboard/lists/index.twig', [
            'lists' => $lists
        ]);
    }
    
    public function create(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'dashboard/lists/create.twig');
    }
    
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $isPublic = isset($data['is_public']);
        
        if (empty($name)) {
            $this->flash->addMessage('error', 'Bitte geben Sie einen Namen ein.');
            return $response->withHeader('Location', '/dashboard/lists/create')->withStatus(302);
        }
        
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $list = new FeedList();
        $list->name = $name;
        $list->slug = FeedList::generateSlug($name);
        $list->user_id = $userId;
        $list->is_public = $isPublic;
        $list->save();
        
        $this->flash->addMessage('success', 'Liste erfolgreich erstellt. Fügen Sie jetzt Quellen hinzu.');
        return $response->withHeader('Location', '/dashboard/lists/' . $list->id . '/sources')->withStatus(302);
    }
    
    public function edit(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        $list = FeedList::where('id', $id)->where('user_id', $userId)->first();
        
        if (!$list) {
            $this->flash->addMessage('error', 'Liste nicht gefunden.');
            return $response->withHeader('Location', '/dashboard/lists')->withStatus(302);
        }
        
        return $this->view->render($response, 'dashboard/lists/edit.twig', [
            'list' => $list
        ]);
    }
    
    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        $list = FeedList::where('id', $id)->where('user_id', $userId)->first();
        
        if (!$list) {
            $this->flash->addMessage('error', 'Liste nicht gefunden.');
            return $response->withHeader('Location', '/dashboard/lists')->withStatus(302);
        }
        
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $isPublic = isset($data['is_public']);
        
        if (empty($name)) {
            $this->flash->addMessage('error', 'Bitte geben Sie einen Namen ein.');
            return $response->withHeader('Location', '/dashboard/lists/' . $id . '/edit')->withStatus(302);
        }
        
        // Only regenerate slug if name changed significantly
        if (strtolower($name) !== strtolower($list->name)) {
            $list->slug = FeedList::generateSlug($name);
        }
        
        $list->name = $name;
        $list->is_public = $isPublic;
        $list->save();
        
        $this->flash->addMessage('success', 'Liste erfolgreich aktualisiert.');
        return $response->withHeader('Location', '/dashboard/lists')->withStatus(302);
    }
    
    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        $list = FeedList::where('id', $id)->where('user_id', $userId)->first();
        
        if (!$list) {
            $this->flash->addMessage('error', 'Liste nicht gefunden.');
            return $response->withHeader('Location', '/dashboard/lists')->withStatus(302);
        }
        
        $list->delete();
        
        $this->flash->addMessage('success', 'Liste erfolgreich gelöscht.');
        return $response->withHeader('Location', '/dashboard/lists')->withStatus(302);
    }
    
    public function manageSources(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        $list = FeedList::where('id', $id)->where('user_id', $userId)->with('sources')->first();
        
        if (!$list) {
            $this->flash->addMessage('error', 'Liste nicht gefunden.');
            return $response->withHeader('Location', '/dashboard/lists')->withStatus(302);
        }
        
        // Get all available sources (from all categories - users can use any source in lists)
        $sources = Source::with('category')->withCount('feedItems')->orderBy('name')->get();
        
        // Get current list sources with filters
        $listSources = ListSource::where('list_id', $id)->get()->keyBy('source_id');
        
        // Get available authors and categories from crawled data for each source
        $sourceFilters = [];
        foreach ($sources as $source) {
            $sourceFilters[$source->id] = [
                'authors' => $this->feedService->getSourceAuthors($source->id),
                'categories' => $this->feedService->getSourceCategories($source->id),
            ];
        }
        
        return $this->view->render($response, 'dashboard/lists/sources.twig', [
            'list' => $list,
            'sources' => $sources,
            'listSources' => $listSources,
            'sourceFilters' => $sourceFilters,
        ]);
    }
    
    public function updateSources(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        $list = FeedList::where('id', $id)->where('user_id', $userId)->first();
        
        if (!$list) {
            $this->flash->addMessage('error', 'Liste nicht gefunden.');
            return $response->withHeader('Location', '/dashboard/lists')->withStatus(302);
        }
        
        $data = $request->getParsedBody();
        $selectedSources = $data['sources'] ?? [];
        $filters = $data['filters'] ?? [];
        
        // Remove all existing sources
        ListSource::where('list_id', $id)->delete();
        
        // Add selected sources with filters
        foreach ($selectedSources as $sourceId) {
            $sourceId = (int) $sourceId;
            
            $listSource = new ListSource();
            $listSource->list_id = $id;
            $listSource->source_id = $sourceId;
            
            if (isset($filters[$sourceId])) {
                // Handle multi-select values (arrays) or text input (strings)
                $authorWhitelist = $filters[$sourceId]['author_whitelist'] ?? null;
                $authorBlacklist = $filters[$sourceId]['author_blacklist'] ?? null;
                $categoryWhitelist = $filters[$sourceId]['category_whitelist'] ?? null;
                $categoryBlacklist = $filters[$sourceId]['category_blacklist'] ?? null;
                
                // Convert arrays to comma-separated strings
                $listSource->author_whitelist = is_array($authorWhitelist) ? implode(',', $authorWhitelist) : (trim($authorWhitelist ?? '') ?: null);
                $listSource->author_blacklist = is_array($authorBlacklist) ? implode(',', $authorBlacklist) : (trim($authorBlacklist ?? '') ?: null);
                $listSource->category_whitelist = is_array($categoryWhitelist) ? implode(',', $categoryWhitelist) : (trim($categoryWhitelist ?? '') ?: null);
                $listSource->category_blacklist = is_array($categoryBlacklist) ? implode(',', $categoryBlacklist) : (trim($categoryBlacklist ?? '') ?: null);
            }
            
            $listSource->save();
        }
        
        $this->flash->addMessage('success', 'Quellen erfolgreich aktualisiert.');
        return $response->withHeader('Location', '/dashboard/lists/' . $id . '/sources')->withStatus(302);
    }
}


<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Source;
use App\Services\FeedService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class SourceController
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
        
        // Show only sources owned by the user
        $sources = Source::where('user_id', $userId)
            ->with('category')
            ->withCount('feedItems')
            ->orderBy('name')
            ->get();
        
        return $this->view->render($response, 'dashboard/sources/index.twig', [
            'sources' => $sources,
            'userId' => $userId
        ]);
    }
    
    public function create(Request $request, Response $response): Response
    {
        // Show all categories for creating sources
        $categories = Category::orderBy('name')->get();
        
        return $this->view->render($response, 'dashboard/sources/create.twig', [
            'categories' => $categories
        ]);
    }
    
    public function store(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $url = trim($data['url'] ?? '');
        $categoryId = (int) ($data['category_id'] ?? 0);
        
        if (empty($name) || empty($url) || $categoryId === 0) {
            $this->flash->addMessage('error', 'Bitte füllen Sie alle Felder aus.');
            return $response->withHeader('Location', '/dashboard/sources/create')->withStatus(302);
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->flash->addMessage('error', 'Bitte geben Sie eine gültige URL ein.');
            return $response->withHeader('Location', '/dashboard/sources/create')->withStatus(302);
        }
        
        // Allow any existing category
        $category = Category::find($categoryId);
        if (!$category) {
            $this->flash->addMessage('error', 'Ungültige Kategorie.');
            return $response->withHeader('Location', '/dashboard/sources/create')->withStatus(302);
        }
        
        // Check if URL already exists for this user
        if (Source::where('user_id', $userId)->where('url', $url)->exists()) {
            $this->flash->addMessage('error', 'Diese RSS-URL existiert bereits.');
            return $response->withHeader('Location', '/dashboard/sources/create')->withStatus(302);
        }
        
        // Validate RSS feed
        if (!$this->feedService->validateFeed($url)) {
            $this->flash->addMessage('error', 'Die angegebene URL ist kein gültiger RSS-Feed.');
            return $response->withHeader('Location', '/dashboard/sources/create')->withStatus(302);
        }
        
        $source = new Source();
        $source->user_id = $userId;
        $source->name = $name;
        $source->url = $url;
        $source->category_id = $categoryId;
        $source->save();
        
        $this->flash->addMessage('success', 'Quelle erfolgreich erstellt.');
        return $response->withHeader('Location', '/dashboard/sources')->withStatus(302);
    }
    
    public function edit(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        // Only allow editing user's own sources
        $source = Source::where('user_id', $userId)->find($id);
        
        if (!$source) {
            $this->flash->addMessage('error', 'Quelle nicht gefunden oder keine Berechtigung.');
            return $response->withHeader('Location', '/dashboard/sources')->withStatus(302);
        }
        
        // Show all categories for editing
        $categories = Category::orderBy('name')->get();
        
        return $this->view->render($response, 'dashboard/sources/edit.twig', [
            'source' => $source,
            'categories' => $categories
        ]);
    }
    
    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        // Only allow updating user's own sources
        $source = Source::where('user_id', $userId)->find($id);
        
        if (!$source) {
            $this->flash->addMessage('error', 'Quelle nicht gefunden oder keine Berechtigung.');
            return $response->withHeader('Location', '/dashboard/sources')->withStatus(302);
        }
        
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $url = trim($data['url'] ?? '');
        $categoryId = (int) ($data['category_id'] ?? 0);
        
        if (empty($name) || empty($url) || $categoryId === 0) {
            $this->flash->addMessage('error', 'Bitte füllen Sie alle Felder aus.');
            return $response->withHeader('Location', '/dashboard/sources/' . $id . '/edit')->withStatus(302);
        }
        
        // Verify category exists
        $category = Category::find($categoryId);
        if (!$category) {
            $this->flash->addMessage('error', 'Ungültige Kategorie.');
            return $response->withHeader('Location', '/dashboard/sources/' . $id . '/edit')->withStatus(302);
        }
        
        // Check URL uniqueness for this user
        if (Source::where('user_id', $userId)->where('url', $url)->where('id', '!=', $id)->exists()) {
            $this->flash->addMessage('error', 'Diese RSS-URL existiert bereits.');
            return $response->withHeader('Location', '/dashboard/sources/' . $id . '/edit')->withStatus(302);
        }
        
        $source->name = $name;
        $source->url = $url;
        $source->category_id = $categoryId;
        $source->save();
        
        $this->flash->addMessage('success', 'Quelle erfolgreich aktualisiert.');
        return $response->withHeader('Location', '/dashboard/sources')->withStatus(302);
    }
    
    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        // Only allow deleting user's own sources
        $source = Source::where('user_id', $userId)->find($id);
        
        if (!$source) {
            $this->flash->addMessage('error', 'Quelle nicht gefunden oder keine Berechtigung.');
            return $response->withHeader('Location', '/dashboard/sources')->withStatus(302);
        }
        
        $source->delete();
        
        $this->flash->addMessage('success', 'Quelle erfolgreich gelöscht.');
        return $response->withHeader('Location', '/dashboard/sources')->withStatus(302);
    }
    
    public function refresh(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $id = (int) $args['id'];
        // Only allow refreshing user's own sources
        $source = Source::where('user_id', $userId)->find($id);
        
        if (!$source) {
            $this->flash->addMessage('error', 'Quelle nicht gefunden oder keine Berechtigung.');
            return $response->withHeader('Location', '/dashboard/sources')->withStatus(302);
        }
        
        $newItems = $this->feedService->refreshSource($source);
        
        if ($newItems >= 0) {
            $this->flash->addMessage('success', "Feed aktualisiert: {$newItems} neue Artikel gefunden.");
        } else {
            $this->flash->addMessage('error', 'Fehler beim Abrufen des Feeds.');
        }
        
        return $response->withHeader('Location', '/dashboard/sources')->withStatus(302);
    }
}

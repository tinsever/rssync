<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class CategoryController
{
    public function __construct(
        private Twig $view,
        private Messages $flash
    ) {}
    
    public function index(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'];
        
        // Only show user's own categories
        $categories = Category::where('user_id', $userId)
            ->withCount('sources')
            ->orderBy('name')
            ->get();
        
        return $this->view->render($response, 'dashboard/categories/index.twig', [
            'categories' => $categories,
            'userId' => $userId
        ]);
    }
    
    public function create(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'dashboard/categories/create.twig');
    }
    
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        
        if (empty($name)) {
            $this->flash->addMessage('error', 'Bitte geben Sie einen Namen ein.');
            return $response->withHeader('Location', '/dashboard/categories/create')->withStatus(302);
        }
        
        $userId = $_SESSION['user_id'];
        
        // Check if category name already exists for this user
        if (Category::where('name', $name)->where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })->exists()) {
            $this->flash->addMessage('error', 'Eine Kategorie mit diesem Namen existiert bereits.');
            return $response->withHeader('Location', '/dashboard/categories/create')->withStatus(302);
        }
        
        $category = new Category();
        $category->name = $name;
        $category->user_id = $userId;
        $category->save();
        
        $this->flash->addMessage('success', 'Kategorie erfolgreich erstellt.');
        return $response->withHeader('Location', '/dashboard/categories')->withStatus(302);
    }
    
    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $userId = $_SESSION['user_id'];
        
        $category = Category::where('id', $id)->where('user_id', $userId)->first();
        
        if (!$category) {
            $this->flash->addMessage('error', 'Kategorie nicht gefunden oder keine Berechtigung.');
            return $response->withHeader('Location', '/dashboard/categories')->withStatus(302);
        }
        
        return $this->view->render($response, 'dashboard/categories/edit.twig', [
            'category' => $category
        ]);
    }
    
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $userId = $_SESSION['user_id'];
        
        $category = Category::where('id', $id)->where('user_id', $userId)->first();
        
        if (!$category) {
            $this->flash->addMessage('error', 'Kategorie nicht gefunden oder keine Berechtigung.');
            return $response->withHeader('Location', '/dashboard/categories')->withStatus(302);
        }
        
        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        
        if (empty($name)) {
            $this->flash->addMessage('error', 'Bitte geben Sie einen Namen ein.');
            return $response->withHeader('Location', '/dashboard/categories/' . $id . '/edit')->withStatus(302);
        }
        
        // Check for duplicate name
        if (Category::where('name', $name)->where('id', '!=', $id)->where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })->exists()) {
            $this->flash->addMessage('error', 'Eine Kategorie mit diesem Namen existiert bereits.');
            return $response->withHeader('Location', '/dashboard/categories/' . $id . '/edit')->withStatus(302);
        }
        
        $category->name = $name;
        $category->save();
        
        $this->flash->addMessage('success', 'Kategorie erfolgreich aktualisiert.');
        return $response->withHeader('Location', '/dashboard/categories')->withStatus(302);
    }
    
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $userId = $_SESSION['user_id'];
        
        $category = Category::where('id', $id)->where('user_id', $userId)->first();
        
        if (!$category) {
            $this->flash->addMessage('error', 'Kategorie nicht gefunden oder keine Berechtigung.');
            return $response->withHeader('Location', '/dashboard/categories')->withStatus(302);
        }
        
        $category->delete();
        
        $this->flash->addMessage('success', 'Kategorie erfolgreich gelöscht.');
        return $response->withHeader('Location', '/dashboard/categories')->withStatus(302);
    }
}


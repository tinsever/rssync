<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Source;
use App\Services\FeedService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiController
{
    public function __construct(
        private FeedService $feedService
    ) {}
    
    public function refreshAll(Request $request, Response $response): Response
    {
        $results = $this->feedService->refreshAll();
        
        $payload = json_encode([
            'success' => true,
            'message' => 'Refresh completed',
            'data' => $results,
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
    
    public function refreshSource(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        
        $source = Source::find($id);
        
        if (!$source) {
            $payload = json_encode([
                'success' => false,
                'message' => 'Source not found',
            ]);
            
            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }
        
        $newItems = $this->feedService->refreshSource($source);
        
        if ($newItems < 0) {
            $payload = json_encode([
                'success' => false,
                'message' => 'Failed to refresh source',
            ]);
            
            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
        
        $payload = json_encode([
            'success' => true,
            'message' => 'Source refreshed',
            'data' => [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'new_items' => $newItems,
            ],
        ]);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}


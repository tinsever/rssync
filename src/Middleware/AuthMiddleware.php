<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check for WorkOS user ID in session
        if (!isset($_SESSION['workos_user_id'])) {
            $response = new Response();
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }
        
        // Add WorkOS user ID to request attributes for controllers
        $request = $request->withAttribute('workos_user_id', $_SESSION['workos_user_id']);
        
        // Also add local user_id for backward compatibility during migration
        if (isset($_SESSION['user_id'])) {
            $request = $request->withAttribute('user_id', $_SESSION['user_id']);
        }
        
        return $handler->handle($request);
    }
}


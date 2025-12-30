<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\WorkOSService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class AuthController
{
    public function __construct(
        private Twig $view,
        private Messages $flash,
        private AuthService $authService,
        private WorkOSService $workOSService
    ) {}
    
    public function showLogin(Request $request, Response $response): Response
    {
        // If already logged in, redirect to dashboard
        if (isset($_SESSION['workos_user_id'])) {
            return $response
                ->withHeader('Location', '/dashboard')
                ->withStatus(302);
        }
        
        // Redirect to WorkOS AuthKit
        return $this->redirectToWorkOS($request, $response);
    }
    
    public function redirectToWorkOS(Request $request, Response $response): Response
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        
        // Also store in cookie as backup (with short expiration)
        setcookie('oauth_state_backup', $state, time() + 600, '/', '', false, true); // 10 minutes, httpOnly
        
        // Log for debugging (will show in terminal with php -S)
        error_log('[AUTH] Setting OAuth state: ' . $state . ' (Session ID: ' . session_id() . ')');
        
        $authUrl = $this->workOSService->getAuthorizationUrl($state);
        
        return $response
            ->withHeader('Location', $authUrl)
            ->withStatus(302);
    }
    
    public function handleCallback(Request $request, Response $response): Response
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // If already logged in, redirect to dashboard (prevent loops)
        if (isset($_SESSION['workos_user_id'])) {
            return $response
                ->withHeader('Location', '/dashboard')
                ->withStatus(302);
        }
        
        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? null;
        $state = $queryParams['state'] ?? null;
        $error = $queryParams['error'] ?? null;
        
        // Log callback received data
        error_log('[AUTH] Callback received - Code: ' . ($code ? 'PRESENT' : 'MISSING') . ', State: ' . ($state ?? 'MISSING') . ', Error: ' . ($error ?? 'NONE'));
        error_log('[AUTH] Full query params: ' . print_r($queryParams, true));
        
        // Check for errors from WorkOS
        if ($error) {
            $this->flash->addMessage('error', 'Authentifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
            // Clear any existing state to prevent loops
            unset($_SESSION['oauth_state']);
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }
        
        // Verify state - check both session and cookie backup
        $sessionState = $_SESSION['oauth_state'] ?? null;
        $cookieState = $_COOKIE['oauth_state_backup'] ?? null;
        $expectedState = $sessionState ?? $cookieState;
        
        // WorkOS SDK JSON-encodes the state, so we need to handle that
        // Try to decode if it's JSON-encoded, otherwise use as-is
        $decodedState = $state;
        if ($state) {
            // Try JSON decoding (WorkOS SDK sends state as JSON string)
            $jsonDecoded = json_decode($state, true);
            if ($jsonDecoded !== null && is_string($jsonDecoded)) {
                $decodedState = $jsonDecoded;
            }
            // If it looks like a JSON string (starts and ends with quotes), try stripping quotes
            elseif (strlen($state) > 2 && $state[0] === '"' && $state[strlen($state) - 1] === '"') {
                $decodedState = substr($state, 1, -1);
            }
        }
        
        // Log state comparison details
        error_log('[AUTH] State comparison - Session: ' . ($sessionState ?? 'NOT SET') . ', Cookie: ' . ($cookieState ?? 'NOT SET') . ', Received (raw): ' . ($state ?? 'NOT SET') . ', Received (decoded): ' . ($decodedState ?? 'NOT SET'));
        error_log('[AUTH] State match check - Session match: ' . ($decodedState === $sessionState ? 'YES' : 'NO') . ', Cookie match: ' . ($decodedState === $cookieState ? 'YES' : 'NO'));
        
        // Compare with decoded state
        if (!$decodedState || !$expectedState || $decodedState !== $expectedState) {
            // Log state mismatch for debugging (will show in terminal with php -S)
            error_log('[AUTH] State mismatch - Expected (session): ' . ($sessionState ?? 'NOT SET') . ', Expected (cookie): ' . ($cookieState ?? 'NOT SET') . ', Received (raw): ' . ($state ?? 'NOT SET') . ', Received (decoded): ' . ($decodedState ?? 'NOT SET'));
            error_log('[AUTH] Session ID: ' . session_id());
            error_log('[AUTH] Session data: ' . print_r($_SESSION, true));
            error_log('[AUTH] Cookie data: ' . print_r($_COOKIE, true));
            
            $this->flash->addMessage('error', 'Ungültiger Authentifizierungsstatus. Bitte versuchen Sie es erneut.');
            // Clear invalid state
            unset($_SESSION['oauth_state']);
            setcookie('oauth_state_backup', '', time() - 3600, '/');
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }
        
        // If we used cookie backup, restore to session
        if ($sessionState === null && $cookieState !== null && $state === $cookieState) {
            $_SESSION['oauth_state'] = $cookieState;
        }
        
        if (!$code) {
            $this->flash->addMessage('error', 'Authentifizierungscode fehlt.');
            // Clear state
            unset($_SESSION['oauth_state']);
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }
        
        try {
            // Exchange code for user info
            $result = $this->workOSService->handleCallback($code);
            $workosUser = $result['user'];
            
            // Check if user already exists and is logged in (prevent duplicate processing)
            if (isset($_SESSION['workos_user_id']) && $_SESSION['workos_user_id'] === $workosUser->id) {
                // Already logged in, just redirect
                return $response
                    ->withHeader('Location', '/dashboard')
                    ->withStatus(302);
            }
            
            // Find or create user in local database
            $user = $this->authService->findOrCreateByWorkOSId(
                $workosUser->id,
                $workosUser->email,
                $workosUser->firstName ?? null,
                $workosUser->lastName ?? null
            );
            
            // Store session data
            $_SESSION['workos_user_id'] = $workosUser->id;
            $_SESSION['user_id'] = $user->id; // Keep for backward compatibility during migration
            $_SESSION['user_email'] = $workosUser->email;
            $_SESSION['access_token'] = $result['access_token'];
            
            if (isset($result['refresh_token'])) {
                $_SESSION['refresh_token'] = $result['refresh_token'];
            }
            
            // Clear OAuth state
            unset($_SESSION['oauth_state']);
            setcookie('oauth_state_backup', '', time() - 3600, '/');
            
            // Check if this was a management redirect
            $redirectTo = '/dashboard';
            if (isset($_SESSION['workos_management_redirect'])) {
                $redirectTo = $_SESSION['workos_management_redirect'];
                unset($_SESSION['workos_management_redirect']);
                $this->flash->addMessage('success', 'Profil erfolgreich aktualisiert!');
            } else {
                $this->flash->addMessage('success', 'Erfolgreich angemeldet!');
            }
            
            return $response
                ->withHeader('Location', $redirectTo)
                ->withStatus(302);
        } catch (\Exception $e) {
            error_log('[AUTH] WorkOS callback error: ' . $e->getMessage());
            error_log('[AUTH] Stack trace: ' . $e->getTraceAsString());
            $this->flash->addMessage('error', 'Fehler bei der Authentifizierung. Bitte versuchen Sie es erneut.');
            // Clear state to prevent loops
            unset($_SESSION['oauth_state']);
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }
    }
    
    public function logout(Request $request, Response $response): Response
    {
        // Clear session data
        unset($_SESSION['workos_user_id']);
        unset($_SESSION['user_id']);
        unset($_SESSION['user_email']);
        unset($_SESSION['access_token']);
        unset($_SESSION['refresh_token']);
        
        session_destroy();
        
        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }
}

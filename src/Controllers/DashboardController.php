<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\FeedItem;
use App\Models\FeedList;
use App\Models\Source;
use App\Models\User;
use App\Services\AuthService;
use App\Services\WorkOSService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class DashboardController
{
    public function __construct(
        private Twig $view,
        private Messages $flash,
        private AuthService $authService,
        private WorkOSService $workOSService
    ) {}
    
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        // Get user stats
        $categoryCount = Category::where('user_id', $userId)->count();
        $sourceCount = Source::whereHas('category', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();
        $listCount = FeedList::where('user_id', $userId)->count();
        
        // Get recent items from user's lists
        $userListIds = FeedList::where('user_id', $userId)->pluck('id');
        
        return $this->view->render($response, 'dashboard/index.twig', [
            'categoryCount' => $categoryCount,
            'sourceCount' => $sourceCount,
            'listCount' => $listCount,
        ]);
    }
    
    public function profile(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $workosUserId = $request->getAttribute('workos_user_id');
        
        if (!$userId || !$workosUserId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $user = User::find($userId);
        
        // Get WorkOS user information
        $workosUser = null;
        try {
            $workosUser = $this->workOSService->getCurrentUserInfo($workosUserId);
        } catch (\Exception $e) {
            error_log('Failed to fetch WorkOS user info: ' . $e->getMessage());
        }
        
        return $this->view->render($response, 'dashboard/profile.twig', [
            'user' => $user,
            'workosUser' => $workosUser,
        ]);
    }
    
    public function refreshWorkOSProfile(Request $request, Response $response): Response
    {
        $workosUserId = $request->getAttribute('workos_user_id');
        
        if (!$workosUserId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        try {
            $workosUser = $this->workOSService->getCurrentUserInfo($workosUserId);
            
            if ($workosUser) {
                // Update local user data
                $user = User::findByWorkOSId($workosUserId);
                if ($user) {
                    $user->email = $workosUser->email;
                    if (isset($workosUser->emailVerified) && $workosUser->emailVerified) {
                        $user->email_verified_at = new \DateTime();
                    }
                    $user->save();
                }
                
                $this->flash->addMessage('success', 'Profilinformationen erfolgreich aktualisiert.');
            }
        } catch (\Exception $e) {
            error_log('Failed to refresh WorkOS profile: ' . $e->getMessage());
            $this->flash->addMessage('error', 'Fehler beim Aktualisieren der Profilinformationen.');
        }
        
        return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
    }
    
    public function redirectToWorkOSManagement(Request $request, Response $response): Response
    {
        // Store a flag to redirect back to profile after WorkOS callback
        $_SESSION['workos_management_redirect'] = '/dashboard/profile';
        
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        setcookie('oauth_state_backup', $state, time() + 600, '/', '', false, true);
        
        try {
            $managementUrl = $this->workOSService->getProfileManagementUrl($state);
            return $response
                ->withHeader('Location', $managementUrl)
                ->withStatus(302);
        } catch (\Exception $e) {
            error_log('Failed to get WorkOS management URL: ' . $e->getMessage());
            $this->flash->addMessage('error', 'Fehler beim Öffnen der WorkOS-Verwaltung.');
            return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
        }
    }
    
    public function updateProfile(Request $request, Response $response): Response
    {
        // Profile updates are handled by WorkOS, so redirect to WorkOS user management
        $this->flash->addMessage('info', 'Profiländerungen werden über WorkOS verwaltet.');
        return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
    }
    
    public function deleteAccount(Request $request, Response $response): Response
    {
        $workosUserId = $request->getAttribute('workos_user_id');
        
        if (!$workosUserId) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        if ($this->authService->deleteAccount($workosUserId)) {
            session_destroy();
            return $response->withHeader('Location', '/')->withStatus(302);
        }
        
        $this->flash->addMessage('error', 'Fehler beim Löschen des Kontos.');
        return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
    }
}


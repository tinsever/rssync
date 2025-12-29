<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\FeedItem;
use App\Models\FeedList;
use App\Models\Source;
use App\Models\User;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class DashboardController
{
    public function __construct(
        private Twig $view,
        private Messages $flash,
        private AuthService $authService
    ) {}
    
    public function index(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'];
        
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
        $user = User::find($_SESSION['user_id']);
        
        return $this->view->render($response, 'dashboard/profile.twig', [
            'user' => $user,
        ]);
    }
    
    public function updateProfile(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'];
        $data = $request->getParsedBody();
        $action = $data['action'] ?? 'email';
        
        if ($action === 'email') {
            $newEmail = trim($data['email'] ?? '');
            
            if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $this->flash->addMessage('error', 'Bitte geben Sie eine gültige E-Mail-Adresse ein.');
                return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
            }
            
            if (!$this->authService->updateEmail($userId, $newEmail)) {
                $this->flash->addMessage('error', 'Diese E-Mail-Adresse wird bereits verwendet.');
                return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
            }
            
            $_SESSION['user_email'] = $newEmail;
            $this->flash->addMessage('success', 'E-Mail-Adresse erfolgreich aktualisiert.');
        } elseif ($action === 'password') {
            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';
            $confirmPassword = $data['confirm_password'] ?? '';
            
            if (strlen($newPassword) < 8) {
                $this->flash->addMessage('error', 'Das neue Passwort muss mindestens 8 Zeichen lang sein.');
                return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
            }
            
            if ($newPassword !== $confirmPassword) {
                $this->flash->addMessage('error', 'Die Passwörter stimmen nicht überein.');
                return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
            }
            
            if (!$this->authService->updatePassword($userId, $currentPassword, $newPassword)) {
                $this->flash->addMessage('error', 'Das aktuelle Passwort ist falsch.');
                return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
            }
            
            $this->flash->addMessage('success', 'Passwort erfolgreich geändert.');
        }
        
        return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
    }
    
    public function deleteAccount(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'];
        
        if ($this->authService->deleteAccount($userId)) {
            session_destroy();
            return $response->withHeader('Location', '/')->withStatus(302);
        }
        
        $this->flash->addMessage('error', 'Fehler beim Löschen des Kontos.');
        return $response->withHeader('Location', '/dashboard/profile')->withStatus(302);
    }
}


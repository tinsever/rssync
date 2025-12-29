<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\AuthService;
use App\Services\EmailService;
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
        private EmailService $emailService
    ) {}
    
    public function showLogin(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'auth/login.twig');
    }
    
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        $user = $this->authService->login($email, $password);
        
        if (!$user) {
            $this->flash->addMessage('error', 'Ungültige Anmeldedaten.');
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }
        
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        
        $this->flash->addMessage('success', 'Erfolgreich angemeldet!');
        return $response
            ->withHeader('Location', '/dashboard')
            ->withStatus(302);
    }
    
    public function showRegister(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'auth/register.twig');
    }
    
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        
        // Validation
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash->addMessage('error', 'Bitte geben Sie eine gültige E-Mail-Adresse ein.');
            return $response->withHeader('Location', '/register')->withStatus(302);
        }
        
        if (strlen($password) < 8) {
            $this->flash->addMessage('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            return $response->withHeader('Location', '/register')->withStatus(302);
        }
        
        if ($password !== $passwordConfirm) {
            $this->flash->addMessage('error', 'Die Passwörter stimmen nicht überein.');
            return $response->withHeader('Location', '/register')->withStatus(302);
        }
        
        // Check if email exists
        if (User::where('email', $email)->exists()) {
            $this->flash->addMessage('error', 'Diese E-Mail-Adresse ist bereits registriert.');
            return $response->withHeader('Location', '/register')->withStatus(302);
        }
        
        $user = $this->authService->register($email, $password);
        
        // Send verification email
        if (!$this->emailService->sendVerificationEmail($email, $user->verification_token)) {
            error_log('Failed to send verification email to: ' . $email);
        }
        
        $this->flash->addMessage('success', 'Registrierung erfolgreich! Bitte bestätigen Sie Ihre E-Mail-Adresse.');
        
        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
    
    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        
        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }
    
    public function verify(Request $request, Response $response, array $args): Response
    {
        $token = $args['token'] ?? '';
        
        if ($this->authService->verify($token)) {
            $this->flash->addMessage('success', 'E-Mail-Adresse erfolgreich bestätigt!');
        } else {
            $this->flash->addMessage('error', 'Ungültiger oder abgelaufener Bestätigungslink.');
        }
        
        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
    
    public function showForgotPassword(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'auth/forgot-password.twig');
    }
    
    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        
        $token = $this->authService->createResetToken($email);
        
        if ($token) {
            // Send password reset email
            if (!$this->emailService->sendPasswordResetEmail($email, $token)) {
                error_log('Failed to send password reset email to: ' . $email);
            }
        }
        
        // Always show success message to prevent email enumeration
        $this->flash->addMessage('success', 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen des Passworts gesendet.');
        
        return $response
            ->withHeader('Location', '/forgot-password')
            ->withStatus(302);
    }
    
    public function showResetPassword(Request $request, Response $response, array $args): Response
    {
        $token = $args['token'] ?? '';
        
        return $this->view->render($response, 'auth/reset-password.twig', [
            'token' => $token
        ]);
    }
    
    public function resetPassword(Request $request, Response $response, array $args): Response
    {
        $token = $args['token'] ?? '';
        $data = $request->getParsedBody();
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        
        if (strlen($password) < 8) {
            $this->flash->addMessage('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            return $response->withHeader('Location', '/reset-password/' . $token)->withStatus(302);
        }
        
        if ($password !== $passwordConfirm) {
            $this->flash->addMessage('error', 'Die Passwörter stimmen nicht überein.');
            return $response->withHeader('Location', '/reset-password/' . $token)->withStatus(302);
        }
        
        if ($this->authService->resetPassword($token, $password)) {
            $this->flash->addMessage('success', 'Passwort erfolgreich zurückgesetzt!');
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        
        $this->flash->addMessage('error', 'Ungültiger oder abgelaufener Reset-Link.');
        return $response->withHeader('Location', '/forgot-password')->withStatus(302);
    }
}

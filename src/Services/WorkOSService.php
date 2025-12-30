<?php

declare(strict_types=1);

namespace App\Services;

use WorkOS\WorkOS;
use WorkOS\UserManagement;
use WorkOS\Exception;

class WorkOSService
{
    private UserManagement $userManagement;
    private string $clientId;
    private string $redirectUri;

    public function __construct()
    {
        $apiKey = $_ENV['WORKOS_API_KEY'] ?? '';
        $this->clientId = $_ENV['WORKOS_CLIENT_ID'] ?? '';
        $this->redirectUri = $_ENV['WORKOS_REDIRECT_URI'] ?? '';

        if (empty($apiKey) || empty($this->clientId) || empty($this->redirectUri)) {
            throw new \RuntimeException('WorkOS configuration is missing. Please set WORKOS_API_KEY, WORKOS_CLIENT_ID, and WORKOS_REDIRECT_URI in your .env file.');
        }

        // Set WorkOS static configuration
        WorkOS::setApiKey($apiKey);
        WorkOS::setClientId($this->clientId);
        
        // Create UserManagement instance (it uses static WorkOS config)
        $this->userManagement = new UserManagement();
    }

    /**
     * Get the authorization URL for WorkOS AuthKit
     */
    public function getAuthorizationUrl(?string $state = null): string
    {
        $state = $state ?? bin2hex(random_bytes(16));
        
        try {
            $url = $this->userManagement->getAuthorizationUrl(
                $this->redirectUri,
                $state,
                UserManagement::AUTHORIZATION_PROVIDER_AUTHKIT
            );
            
            // Log the generated URL for debugging
            error_log('[AUTH] Generated authorization URL with state: ' . $state);
            error_log('[AUTH] Full URL: ' . $url);
            
            return $url;
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to get authorization URL: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Handle the OAuth callback and exchange code for session
     */
    public function handleCallback(string $code): array
    {
        try {
            // Exchange authorization code for session
            $session = $this->userManagement->authenticateWithCode($this->clientId, $code);

            // Get user information from the session
            $user = $this->userManagement->getUser($session->user->id);

            return [
                'user' => $user,
                'access_token' => $session->accessToken,
                'refresh_token' => $session->refreshToken ?? null,
            ];
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to authenticate with WorkOS: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get user by WorkOS user ID
     */
    public function getUserById(string $userId): object
    {
        try {
            return $this->userManagement->getUser($userId);
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to get user from WorkOS: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a user in WorkOS (for migration)
     */
    public function createUser(string $email, ?string $firstName = null, ?string $lastName = null, bool $emailVerified = false): object
    {
        try {
            return $this->userManagement->createUser(
                $email,
                null, // password
                $firstName,
                $lastName,
                $emailVerified
            );
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to create user in WorkOS: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get current user information from WorkOS
     */
    public function getCurrentUserInfo(string $workosUserId): ?object
    {
        try {
            return $this->userManagement->getUser($workosUserId);
        } catch (Exception $e) {
            error_log('[WorkOS] Failed to get user info: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update user information in WorkOS
     */
    public function updateUser(string $workosUserId, ?string $email = null, ?string $firstName = null, ?string $lastName = null, ?bool $emailVerified = null): object
    {
        try {
            return $this->userManagement->updateUser(
                $workosUserId,
                $firstName,
                $lastName,
                $emailVerified,
                null, // password
                null, // passwordHash
                null, // passwordHashType
                null, // externalId
                null, // metadata
                $email
            );
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to update user in WorkOS: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get authorization URL for profile management (re-authentication)
     * WorkOS AuthKit will show profile management options when user is already authenticated
     */
    public function getProfileManagementUrl(?string $state = null): string
    {
        $state = $state ?? bin2hex(random_bytes(16));
        
        try {
            // Redirect to AuthKit - if user is already logged in, they'll see profile options
            return $this->userManagement->getAuthorizationUrl(
                $this->redirectUri,
                $state,
                UserManagement::AUTHORIZATION_PROVIDER_AUTHKIT
            );
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to get profile management URL: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Verify a session token (for middleware)
     */
    public function verifySession(string $accessToken): ?object
    {
        try {
            // WorkOS doesn't have a direct verify method, but we can try to get user info
            // In production, you might want to use JWK verification
            // For now, we'll store the token and validate it when needed
            return null; // This will be handled by storing user info in session
        } catch (Exception $e) {
            return null;
        }
    }
}


<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class AuthService
{
    /**
     * Find or create a user by WorkOS user ID
     */
    public function findOrCreateByWorkOSId(string $workosUserId, string $email, ?string $firstName = null, ?string $lastName = null): User
    {
        $user = User::findByWorkOSId($workosUserId);
        
        if ($user) {
            // Update email if it changed
            if ($user->email !== $email) {
                $user->email = $email;
                $user->save();
            }
            return $user;
        }
        
        // Check if user exists by email (for migration scenarios)
        $user = User::where('email', $email)->first();
        
        if ($user) {
            // Link existing user to WorkOS
            $user->workos_user_id = $workosUserId;
            $user->email_verified_at = new \DateTime(); // WorkOS handles verification
            $user->save();
            return $user;
        }
        
        // Create new user
        $user = new User();
        $user->workos_user_id = $workosUserId;
        $user->email = $email;
        $user->email_verified_at = new \DateTime(); // WorkOS handles verification
        $user->save();
        
        return $user;
    }
    
    public function getCurrentUser(): ?User
    {
        if (!isset($_SESSION['workos_user_id'])) {
            return null;
        }
        
        return User::findByWorkOSId($_SESSION['workos_user_id']);
    }
    
    public function deleteAccount(string $workosUserId): bool
    {
        $user = User::findByWorkOSId($workosUserId);
        
        if (!$user) {
            return false;
        }
        
        $user->delete();
        return true;
    }
}


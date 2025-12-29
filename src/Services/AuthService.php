<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class AuthService
{
    public function register(string $email, string $password): User
    {
        $user = new User();
        $user->email = $email;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->verification_token = bin2hex(random_bytes(32));
        $user->save();
        
        return $user;
    }
    
    public function login(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();
        
        if (!$user || !password_verify($password, $user->password)) {
            return null;
        }
        
        return $user;
    }
    
    public function verify(string $token): bool
    {
        $user = User::where('verification_token', $token)->first();
        
        if (!$user) {
            return false;
        }
        
        $user->email_verified_at = new \DateTime();
        $user->verification_token = null;
        $user->save();
        
        return true;
    }
    
    public function createResetToken(string $email): ?string
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return null;
        }
        
        $token = bin2hex(random_bytes(32));
        $user->reset_token = $token;
        $user->reset_token_expires = (new \DateTime())->modify('+2 hours');
        $user->save();
        
        return $token;
    }
    
    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = User::where('reset_token', $token)
            ->where('reset_token_expires', '>', new \DateTime())
            ->first();
        
        if (!$user) {
            return false;
        }
        
        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->reset_token = null;
        $user->reset_token_expires = null;
        $user->save();
        
        return true;
    }
    
    public function updatePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = User::find($userId);
        
        if (!$user || !password_verify($currentPassword, $user->password)) {
            return false;
        }
        
        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->save();
        
        return true;
    }
    
    public function updateEmail(int $userId, string $newEmail): bool
    {
        $user = User::find($userId);
        
        if (!$user) {
            return false;
        }
        
        // Check if email is already in use
        if (User::where('email', $newEmail)->where('id', '!=', $userId)->exists()) {
            return false;
        }
        
        $user->email = $newEmail;
        $user->email_verified_at = null;
        $user->verification_token = bin2hex(random_bytes(32));
        $user->save();
        
        return true;
    }
    
    public function deleteAccount(int $userId): bool
    {
        $user = User::find($userId);
        
        if (!$user) {
            return false;
        }
        
        $user->delete();
        return true;
    }
    
    public function getCurrentUser(): ?User
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        return User::find($_SESSION['user_id']);
    }
}


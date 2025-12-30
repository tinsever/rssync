<?php

declare(strict_types=1);

/**
 * Migration script to migrate existing users to WorkOS
 * 
 * Usage: php scripts/migrate-users-to-workos.php
 * 
 * This script will:
 * 1. Export all users from the local database
 * 2. Create corresponding users in WorkOS
 * 3. Map local user IDs to WorkOS user IDs
 * 4. Update the workos_user_id column in the database
 */

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Boot Eloquent
use Illuminate\Database\Capsule\Manager as Capsule;
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'rssync',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

use App\Models\User;
use App\Services\WorkOSService;

// Check if WorkOS is configured
if (empty($_ENV['WORKOS_API_KEY']) || empty($_ENV['WORKOS_CLIENT_ID'])) {
    echo "ERROR: WorkOS configuration is missing. Please set WORKOS_API_KEY and WORKOS_CLIENT_ID in your .env file.\n";
    exit(1);
}

try {
    $workOSService = new WorkOSService();
} catch (\Exception $e) {
    echo "ERROR: Failed to initialize WorkOS service: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Starting user migration to WorkOS...\n\n";

// Get all users from database
$users = User::all();

if ($users->isEmpty()) {
    echo "No users found in database.\n";
    exit(0);
}

echo "Found " . $users->count() . " users to migrate.\n\n";

$successCount = 0;
$errorCount = 0;
$skippedCount = 0;

foreach ($users as $user) {
    echo "Processing user ID {$user->id} ({$user->email})... ";
    
    // Skip if already migrated
    if (!empty($user->workos_user_id)) {
        echo "SKIPPED (already has workos_user_id: {$user->workos_user_id})\n";
        $skippedCount++;
        continue;
    }
    
    try {
        // Create user in WorkOS
        $workosUser = $workOSService->createUser(
            $user->email,
            null, // firstName - not stored in current schema
            null, // lastName - not stored in current schema
            $user->email_verified_at !== null // emailVerified
        );
        
        // Update local user with WorkOS user ID
        $user->workos_user_id = $workosUser->id;
        $user->save();
        
        echo "SUCCESS (WorkOS ID: {$workosUser->id})\n";
        $successCount++;
        
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errorCount++;
        
        // If it's a duplicate email error, try to find existing user in WorkOS
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'duplicate') !== false) {
            echo "  -> User may already exist in WorkOS. Please manually link the user.\n";
        }
    }
}

echo "\n";
echo "Migration completed!\n";
echo "  Success: {$successCount}\n";
echo "  Skipped: {$skippedCount}\n";
echo "  Errors: {$errorCount}\n";

if ($errorCount > 0) {
    echo "\nWARNING: Some users failed to migrate. Please review the errors above.\n";
    exit(1);
}

echo "\nAll users have been successfully migrated to WorkOS!\n";
exit(0);


<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configure error logging for PHP built-in server
if ($_ENV['APP_DEBUG'] === 'true') {
    // Enable error logging
    ini_set('log_errors', '1');
    ini_set('display_errors', '0'); // Don't display in HTML, but log them
    
    // Create log file in storage directory
    $logFile = __DIR__ . '/../storage/logs/app.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    ini_set('error_log', $logFile);
    
    // Also output to stderr (visible in terminal when using php -S)
    // Note: php -S automatically shows errors in terminal, but we'll log to file too
}

// Boot Eloquent early (before container)
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

// Build Container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

// Create App with Container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(
    $_ENV['APP_DEBUG'] === 'true',
    true,
    true
);

// Add Session Middleware
$app->add(new \App\Middleware\SessionMiddleware());

// Register Routes
(require __DIR__ . '/../config/routes.php')($app);

$app->run();


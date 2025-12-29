<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Container\ContainerInterface;
use Slim\Flash\Messages;
use Slim\Views\Twig;

return [
    // Twig View
    Twig::class => function (ContainerInterface $container) {
        $twig = Twig::create(__DIR__ . '/../templates', [
            'cache' => $_ENV['APP_DEBUG'] === 'true' ? false : __DIR__ . '/../storage/cache/twig',
            'debug' => $_ENV['APP_DEBUG'] === 'true',
        ]);
        
        // Add global variables
        $twig->getEnvironment()->addGlobal('app_name', 'RSSync');
        $twig->getEnvironment()->addGlobal('flash', $container->get(Messages::class));
        
        // Add session access function for dynamic access
        $sessionFunction = new \Twig\TwigFunction('session', function () {
            return $_SESSION ?? [];
        });
        $twig->getEnvironment()->addFunction($sessionFunction);
        
        // Also add as global for backward compatibility (will be evaluated at render time via reference)
        $twig->getEnvironment()->addGlobal('session', $_SESSION ?? []);
        
        return $twig;
    },
    
    // Flash Messages
    Messages::class => function () {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return new Messages();
    },
    
    // Database (Eloquent)
    Capsule::class => function () {
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
        
        return $capsule;
    },
    
    // Auth Service
    \App\Services\AuthService::class => function (ContainerInterface $container) {
        $container->get(Capsule::class); // Boot Eloquent
        return new \App\Services\AuthService();
    },
    
    // Feed Service
    \App\Services\FeedService::class => function (ContainerInterface $container) {
        $container->get(Capsule::class); // Boot Eloquent
        return new \App\Services\FeedService();
    },
    
    // Email Service
    \App\Services\EmailService::class => function () {
        return new \App\Services\EmailService();
    },
];


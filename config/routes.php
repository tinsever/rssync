<?php

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\DashboardController;
use App\Controllers\FeedListController;
use App\Controllers\HomeController;
use App\Controllers\SourceController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app) {
    // Add Twig Middleware
    $app->add(TwigMiddleware::createFromContainer($app, Twig::class));
    
    // Public Routes
    $app->get('/', [HomeController::class, 'index'])->setName('home');
    $app->get('/sources', [HomeController::class, 'sources'])->setName('public.sources');
    $app->get('/source/{id}', [HomeController::class, 'viewSource'])->setName('public.source.view');
    $app->get('/lists', [HomeController::class, 'lists'])->setName('public.lists');
    $app->get('/list/{slug}', [HomeController::class, 'viewList'])->setName('public.list.view');
    $app->get('/list/{slug}/rss', [HomeController::class, 'listRss'])->setName('public.list.rss');
    
    // Auth Routes (WorkOS)
    $app->get('/login', [AuthController::class, 'showLogin'])->setName('login');
    $app->get('/auth/callback', [AuthController::class, 'handleCallback'])->setName('auth.callback');
    $app->get('/logout', [AuthController::class, 'logout'])->setName('logout');
    
    // Protected Dashboard Routes
    $app->group('/dashboard', function (RouteCollectorProxy $group) {
        $group->get('', [DashboardController::class, 'index'])->setName('dashboard');
        $group->get('/profile', [DashboardController::class, 'profile'])->setName('dashboard.profile');
        $group->post('/profile', [DashboardController::class, 'updateProfile']);
        $group->get('/profile/refresh', [DashboardController::class, 'refreshWorkOSProfile'])->setName('dashboard.profile.refresh');
        $group->get('/profile/workos', [DashboardController::class, 'redirectToWorkOSManagement'])->setName('dashboard.profile.workos');
        $group->post('/profile/delete', [DashboardController::class, 'deleteAccount'])->setName('dashboard.profile.delete');
        
        // Categories
        $group->get('/categories', [CategoryController::class, 'index'])->setName('dashboard.categories');
        $group->get('/categories/create', [CategoryController::class, 'create'])->setName('dashboard.categories.create');
        $group->post('/categories', [CategoryController::class, 'store']);
        $group->get('/categories/{id}/edit', [CategoryController::class, 'edit'])->setName('dashboard.categories.edit');
        $group->post('/categories/{id}', [CategoryController::class, 'update']);
        $group->post('/categories/{id}/delete', [CategoryController::class, 'delete'])->setName('dashboard.categories.delete');
        
        // Sources
        $group->get('/sources', [SourceController::class, 'index'])->setName('dashboard.sources');
        $group->get('/sources/create', [SourceController::class, 'create'])->setName('dashboard.sources.create');
        $group->post('/sources', [SourceController::class, 'store']);
        $group->get('/sources/{id}/edit', [SourceController::class, 'edit'])->setName('dashboard.sources.edit');
        $group->post('/sources/{id}', [SourceController::class, 'update']);
        $group->post('/sources/{id}/delete', [SourceController::class, 'delete'])->setName('dashboard.sources.delete');
        $group->post('/sources/{id}/refresh', [SourceController::class, 'refresh'])->setName('dashboard.sources.refresh');
        
        // Feed Lists
        $group->get('/lists', [FeedListController::class, 'index'])->setName('dashboard.lists');
        $group->get('/lists/create', [FeedListController::class, 'create'])->setName('dashboard.lists.create');
        $group->post('/lists', [FeedListController::class, 'store']);
        $group->get('/lists/{id}/edit', [FeedListController::class, 'edit'])->setName('dashboard.lists.edit');
        $group->post('/lists/{id}', [FeedListController::class, 'update']);
        $group->post('/lists/{id}/delete', [FeedListController::class, 'delete'])->setName('dashboard.lists.delete');
        $group->get('/lists/{id}/sources', [FeedListController::class, 'manageSources'])->setName('dashboard.lists.sources');
        $group->post('/lists/{id}/sources', [FeedListController::class, 'updateSources']);
    })->add(AuthMiddleware::class);
    
    // API Routes (for cron jobs)
    $app->group('/api', function (RouteCollectorProxy $group) {
        $group->get('/refresh/all', [ApiController::class, 'refreshAll'])->setName('api.refresh.all');
        $group->get('/refresh/{id}', [ApiController::class, 'refreshSource'])->setName('api.refresh.source');
    });
};


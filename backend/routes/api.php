<?php

use App\Http\Controllers\Api\Admin\AiConfigController;
use App\Http\Controllers\Api\Admin\ComparisonController;
use App\Http\Controllers\Api\Admin\GarageAdminController;
use App\Http\Controllers\Api\Admin\SiteThemeController;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GarageController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\RenderController;
use App\Http\Controllers\Api\ShowroomController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('shots/{id}/overlay', [ShowroomController::class, 'overlay'])->whereNumber('id');
    Route::get('public/{slug}/listings', [PublicController::class, 'listings']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::middleware('role:super')->prefix('admin')->group(function () {
            Route::get('overview', OverviewController::class);
            Route::post('garages', [GarageAdminController::class, 'store']);
            Route::patch('garages/{garage}', [GarageAdminController::class, 'update']);
            Route::get('garages/{garage}/site-theme', [SiteThemeController::class, 'index']);
            Route::put('garages/{garage}/site-theme', [SiteThemeController::class, 'update']);
            Route::get('ai-configs', [AiConfigController::class, 'index']);
            Route::post('ai-configs', [AiConfigController::class, 'store']);
            Route::patch('ai-configs/{aiConfig}', [AiConfigController::class, 'update']);
            Route::get('comparisons', [ComparisonController::class, 'index']);
            Route::post('comparisons', [ComparisonController::class, 'store']);
            Route::get('comparisons/{comparison}', [ComparisonController::class, 'show']);
            Route::post('comparisons/{comparison}/run', [ComparisonController::class, 'run']);
        });

        // Sans garage imposé : un super-admin note ou relance les rendus d'un comparatif depuis l'espace Mending.
        Route::middleware('role:manager')->group(function () {
            Route::post('renders/{id}/rate', [RenderController::class, 'rate'])->whereNumber('id');
            Route::post('renders/{id}/retry', [RenderController::class, 'retry'])->whereNumber('id');
        });

        Route::middleware('garage')->group(function () {
            // Accessibles aussi aux employés (photos)
            Route::get('dashboard', DashboardController::class);
            Route::get('vehicles', [VehicleController::class, 'index']);
            Route::post('vehicles', [VehicleController::class, 'store']);
            Route::get('vehicles/{id}', [VehicleController::class, 'show'])->whereNumber('id');
            Route::get('lookup/plate/{plate}', [VehicleController::class, 'lookup']);
            Route::post('vehicles/{id}/uploads', [PhotoController::class, 'uploads'])->whereNumber('id');
            Route::post('vehicles/{id}/photos', [PhotoController::class, 'store'])->whereNumber('id');
            Route::post('vehicles/{id}/renders', [PhotoController::class, 'renders'])->whereNumber('id');
            // Une photo ratée se supprime depuis l'appli, juste après la prise de vue.
            Route::delete('photos/{id}', [PhotoController::class, 'destroy'])->whereNumber('id');

            Route::middleware('role:manager')->group(function () {
                Route::patch('vehicles/{id}', [VehicleController::class, 'update'])->whereNumber('id');
                Route::delete('vehicles/{id}', [VehicleController::class, 'destroy'])->whereNumber('id');
                Route::post('vehicles/{id}/price', [VehicleController::class, 'price'])->whereNumber('id');
                Route::post('vehicles/{id}/sold', [VehicleController::class, 'sold'])->whereNumber('id');
                Route::post('vehicles/{id}/publish', [VehicleController::class, 'publish'])->whereNumber('id');
                Route::post('vehicles/{id}/history', [VehicleController::class, 'history'])->whereNumber('id');
                Route::post('pricing/advice', [VehicleController::class, 'priceAdvice'])->middleware('throttle:20,1');
                Route::post('vehicles/{id}/description', [VehicleController::class, 'description'])->whereNumber('id');
                Route::post('vehicles/{id}/approve-all', [RenderController::class, 'approveAll'])->whereNumber('id');
                Route::get('vehicles/{id}/export', [VehicleController::class, 'export'])->whereNumber('id');
                Route::patch('photos/{id}', [PhotoController::class, 'update'])->whereNumber('id');

                Route::post('renders/{id}/approve', [RenderController::class, 'approve'])->whereNumber('id');
                Route::post('renders/{id}/keep-original', [RenderController::class, 'keepOriginal'])->whereNumber('id');

                Route::get('showrooms', [ShowroomController::class, 'index']);
                Route::patch('shots/{id}', [ShowroomController::class, 'update'])->whereNumber('id');
                Route::post('shots/{id}/image', [ShowroomController::class, 'image'])->whereNumber('id');

                Route::get('leads', [LeadController::class, 'index']);
                Route::patch('leads/{id}', [LeadController::class, 'update'])->whereNumber('id');

                Route::get('garage', [GarageController::class, 'show']);
                Route::patch('garage', [GarageController::class, 'update']);
                Route::post('garage/logo', [GarageController::class, 'logo']);

                Route::get('users', [UserController::class, 'index']);
                Route::post('users', [UserController::class, 'store']);
                Route::delete('users/{id}', [UserController::class, 'destroy'])->whereNumber('id');

                Route::get('usage', UsageController::class);
            });
        });
    });
});

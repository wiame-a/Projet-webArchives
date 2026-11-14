<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentPermissionController;
use App\Http\Controllers\Api\DocumentValidationController;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AddArchiviumHeaders;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

/*
 | Toutes les routes sont préfixées par /api (voir bootstrap/app.php, installé par `php artisan install:api`).
 */
Route::middleware([ForceJsonResponse::class, AddArchiviumHeaders::class])->group(function () {

    // --- Public ---------------------------------------------------------
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // anti force brute

    // --- Authentifié (token Sanctum) -----------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('dashboard', DashboardController::class);

        // Mon compte
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'password']);

        // Journal d'activité : global (gestionnaires) et par document (propriétaire / gestionnaires)
        Route::get('activity', [ActivityController::class, 'index']);
        Route::get('documents/{document}/activity', [ActivityController::class, 'forDocument']);

        // Utilisateurs : "lookup" pour tous (boîte de partage), le reste réservé aux admins (UserPolicy)
        Route::get('users/lookup', [UserController::class, 'lookup']);
        Route::get('users', [UserController::class, 'index']);
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        // Catégories (lecture : tous ; écriture : admin via CategoryPolicy)
        Route::apiResource('categories', CategoryController::class)->except(['show']);

        // Documents : "search" doit être déclaré AVANT la route documents/{document}
        Route::get('documents/search', [DocumentController::class, 'index']);
        Route::apiResource('documents', DocumentController::class);
        Route::patch('documents/{document}/archive', [DocumentController::class, 'archive']);
        Route::patch('documents/{document}/restore', [DocumentController::class, 'restore']);

        // Circuit de validation (administrateur / archiviste)
        Route::patch('documents/{document}/validate', [DocumentValidationController::class, 'approve']);
        Route::patch('documents/{document}/reject', [DocumentValidationController::class, 'reject']);
        Route::patch('documents/{document}/reopen', [DocumentValidationController::class, 'reopen']);

        // Versions
        Route::get('documents/{document}/versions', [DocumentVersionController::class, 'index']);
        Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store']);
        Route::get('documents/{document}/versions/{version}/download', [DocumentVersionController::class, 'download'])
            ->scopeBindings(); // la version doit appartenir au document de l'URL

        // Partages (droits d'accès)
        Route::get('documents/{document}/permissions', [DocumentPermissionController::class, 'index']);
        Route::post('documents/{document}/permissions', [DocumentPermissionController::class, 'store']);
        Route::delete('documents/{document}/permissions/{user}', [DocumentPermissionController::class, 'destroy']);
    });
});

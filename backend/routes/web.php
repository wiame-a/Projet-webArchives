<?php

use Illuminate\Support\Facades\Route;

// Page d'accueil du serveur : l'interface est dans le dossier frontend/ (React).
Route::get('/', fn () => response()->json([
    'application' => config('app.name'),
    'status' => 'API en ligne',
    'api' => url('/api'),
]));

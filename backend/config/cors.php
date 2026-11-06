<?php

/*
 | CORS : autorise le frontend React (port 5173 de Vite par défaut) à appeler l'API.
 | En production, mettre l'URL réelle du frontend dans FRONTEND_URL (.env),
 | plusieurs origines possibles séparées par des virgules.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('FRONTEND_URL', 'http://localhost:5173,http://127.0.0.1:5173'))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    'supports_credentials' => false, // authentification par Bearer token, pas par cookie
];

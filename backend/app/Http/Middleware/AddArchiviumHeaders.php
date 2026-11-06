<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes ajoutés à toutes les réponses de l'API :
 *  - X-Application : nom de l'application (APP_NAME, "Archivium")
 *  - en-têtes de sécurité de base (la réponse d'un contrôleur, par exemple
 *    le téléchargement de fichier, garde les siens s'il en a déjà posé).
 */
class AddArchiviumHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Application' => config('app.name', 'Archivium'),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}

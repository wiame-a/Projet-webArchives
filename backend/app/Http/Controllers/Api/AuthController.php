<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Options JSON : si une valeur contient des octets non UTF-8 (fichier .env
     * enregistré en ANSI / UTF-16, donnée mal encodée en base), l'octet fautif est
     * remplacé au lieu de faire échouer toute la réponse ("Malformed UTF-8").
     */
    private const JSON_OPTIONS = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;

    private function appName(): string
    {
        return mb_convert_encoding((string) config('app.name', 'Archivium'), 'UTF-8', 'UTF-8');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        // Le rôle n'est pas mass-assignable : un nouveau compte est toujours "user".
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return response()->json([
            'message' => 'Bienvenue sur '.$this->appName().', '.$user->name.' ! Votre compte a bien été créé.',
            'user' => new UserResource($user->fresh()),
            'token' => $user->createToken('spa')->plainTextToken,
        ], 201, [], self::JSON_OPTIONS);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Même message que l'email existe ou non (pas d'énumération de comptes).
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        return response()->json([
            'message' => 'Content de vous revoir sur '.$this->appName().', '.$user->name.'.',
            'user' => new UserResource($user),
            'token' => $user->createToken('spa')->plainTextToken,
        ], 200, [], self::JSON_OPTIONS);
    }

    public function logout(Request $request): JsonResponse
    {
        // Révoque uniquement le token utilisé pour cette requête.
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Déconnexion réussie. À bientôt sur '.$this->appName().'.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Compte de l'utilisateur connecté : nom, email et mot de passe. Le rôle n'est jamais modifiable ici. */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        ActivityLogger::log($user, ActivityLog::PROFILE_UPDATED, 'a modifié ses informations de profil.');

        return new UserResource($user->fresh());
    }

    public function password(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }

        $user->password = $request->validated('password'); // haché automatiquement (cast « hashed »)
        $user->save();

        // Sécurité : toutes les AUTRES sessions sont déconnectées, celle-ci reste ouverte.
        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        ActivityLogger::log($user, ActivityLog::PASSWORD_CHANGED, 'a changé son mot de passe.');

        return response()->json(['message' => 'Votre mot de passe a bien été modifié.']);
    }
}

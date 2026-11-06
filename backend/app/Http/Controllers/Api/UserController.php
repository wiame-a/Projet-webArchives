<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /** Administration : liste paginée des comptes. */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->withCount('documents')
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Recherche légère pour la boîte de partage (tout utilisateur connecté).
     * Ne renvoie que id, nom, email ; 10 résultats max ; 2 caractères minimum.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse|UserResource
    {
        Gate::authorize('update', $user);

        if ($user->is($request->user())) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre rôle.'], 422);
        }

        $user->role = $request->validated('role'); // le rôle n'est pas mass-assignable
        $user->save();

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user): Response|JsonResponse
    {
        Gate::authorize('delete', $user);

        if ($user->is($request->user())) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        if ($user->documents()->exists()) {
            return response()->json([
                'message' => 'Suppression impossible : cet utilisateur possède encore des documents.',
            ], 409);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }
}

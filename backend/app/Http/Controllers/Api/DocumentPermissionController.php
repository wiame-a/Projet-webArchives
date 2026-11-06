<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShareDocumentRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentPermissionController extends Controller
{
    public function index(Document $document)
    {
        Gate::authorize('share', $document);

        return PermissionResource::collection($document->permissions()->with('user:id,name,email')->get());
    }

    /** Crée le partage, ou met à jour le droit s'il existe déjà. */
    public function store(ShareDocumentRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('share', $document);

        $data = $request->validated();

        if ((int) $data['user_id'] === (int) $document->user_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Le propriétaire a déjà tous les droits sur ce document.',
            ]);
        }

        $permission = DocumentPermission::updateOrCreate(
            ['document_id' => $document->id, 'user_id' => $data['user_id']],
            ['droit' => $data['droit']]
        );

        return (new PermissionResource($permission->load('user:id,name,email')))
            ->response()
            ->setStatusCode($permission->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Document $document, User $user): Response|JsonResponse
    {
        Gate::authorize('share', $document);

        $deleted = $document->permissions()->where('user_id', $user->id)->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Ce document n\'est pas partagé avec cet utilisateur.'], 404);
        }

        return response()->noContent();
    }
}

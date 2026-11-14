<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchDocumentRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Services\ActivityLogger;
use App\Services\DocumentVersionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentVersionService $versions)
    {
    }

    /**
     * Liste + recherche multicritères (GET /documents et GET /documents/search).
     * Les filtres sont combinés (ET) et ne portent QUE sur les documents
     * accessibles à l'utilisateur connecté.
     */
    public function index(SearchDocumentRequest $request)
    {
        $user = $request->user();
        $f = $request->validated();

        $query = Document::query()
            ->accessibleBy($user)
            ->with([
                'category',
                'owner:id,name',
                'validator:id,name',
                'latestVersion',
                // On ne charge que le partage de l'utilisateur courant (calcul du droit sans N+1).
                'permissions' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->withCount('versions');

        $query
            ->when($f['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.$term.'%';
                $q->where(fn (Builder $w) => $w
                    ->where('titre', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('mots_cles', 'like', $like));
            })
            ->when($f['titre'] ?? null, fn (Builder $q, string $v) => $q->where('titre', 'like', '%'.$v.'%'))
            ->when($f['mots_cles'] ?? null, function (Builder $q, string $v) {
                // Chaque mot-clé saisi doit être présent (ET).
                foreach (array_filter(array_map('trim', explode(',', $v))) as $word) {
                    $q->where('mots_cles', 'like', '%'.$word.'%');
                }
            })
            ->when($f['category_id'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($f['user_id'] ?? null, fn (Builder $q, $v) => $q->where('documents.user_id', $v))
            ->when($f['statut'] ?? null, fn (Builder $q, $v) => $q->where('statut', $v))
            ->when($f['validation'] ?? null, fn (Builder $q, $v) => $q->where('validation', $v))
            ->when($f['date_debut'] ?? null, fn (Builder $q, $v) => $q->whereDate('documents.created_at', '>=', $v))
            ->when($f['date_fin'] ?? null, fn (Builder $q, $v) => $q->whereDate('documents.created_at', '<=', $v))
            ->when(($f['portee'] ?? null) === 'mes', fn (Builder $q) => $q->where('documents.user_id', $user->id))
            ->when(($f['portee'] ?? null) === 'partages', fn (Builder $q) => $q
                ->where('documents.user_id', '!=', $user->id)
                ->whereHas('permissions', fn (Builder $p) => $p->where('user_id', $user->id)));

        $query->orderBy('documents.'.($f['tri'] ?? 'created_at'), $f['ordre'] ?? 'desc')
            ->orderByDesc('documents.id');

        return DocumentResource::collection(
            $query->paginate($f['per_page'] ?? 10)->withQueryString()
        );
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $user = $request->user();

        $document = DB::transaction(function () use ($request, $user) {
            $document = new Document($request->safe()->only(['titre', 'reference', 'description', 'mots_cles', 'category_id']));
            $document->user_id = $user->id;
            $document->statut = Document::STATUT_ACTIF;
            $document->save();

            // La première version (n° 1) est créée avec le document.
            $this->versions->addVersion($document, $user, $request->file('fichier'));

            return $document;
        });

        ActivityLogger::log($user, ActivityLog::DOCUMENT_CREATED, 'a créé le document '.ActivityLogger::label($document).'.', $document);

        $document->load(['category', 'owner:id,name', 'validator:id,name', 'latestVersion']);

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function show(Request $request, Document $document): DocumentResource
    {
        Gate::authorize('view', $document);

        $document->load(['category', 'owner:id,name', 'validator:id,name', 'latestVersion', 'versions.author:id,name']);

        // La liste complète des partages n'est visible que par ceux qui peuvent partager.
        if (Gate::forUser($request->user())->allows('share', $document)) {
            $document->load('permissions.user:id,name,email');
        } else {
            $document->load(['permissions' => fn ($q) => $q->where('user_id', $request->user()->id)]);
        }

        return new DocumentResource($document);
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        Gate::authorize('update', $document); // refuse aussi si le document est archivé

        $document->update($request->validated());

        ActivityLogger::log($request->user(), ActivityLog::DOCUMENT_UPDATED, 'a modifié les informations du document '.ActivityLogger::label($document).'.', $document);

        return new DocumentResource($document->load(['category', 'owner:id,name', 'validator:id,name', 'latestVersion']));
    }

    public function destroy(Request $request, Document $document): Response
    {
        Gate::authorize('delete', $document);

        $id = $document->id;
        $label = ActivityLogger::label($document); // à capturer avant la suppression
        $document->delete(); // les versions et partages partent en cascade (clés étrangères)
        $this->versions->deleteFiles($id);

        // Sans lien vers le document (il n'existe plus) : la phrase garde son titre et son code.
        ActivityLogger::log($request->user(), ActivityLog::DOCUMENT_DELETED, 'a supprimé le document '.$label.'.');

        return response()->noContent();
    }

    public function archive(Request $request, Document $document): JsonResponse|DocumentResource
    {
        Gate::authorize('archive', $document);

        if ($document->isArchived()) {
            return response()->json(['message' => 'Ce document est déjà archivé.'], 409);
        }

        $document->statut = Document::STATUT_ARCHIVE;
        $document->archived_at = now();
        $document->save();

        ActivityLogger::log($request->user(), ActivityLog::DOCUMENT_ARCHIVED, 'a archivé le document '.ActivityLogger::label($document).'.', $document);

        return new DocumentResource($document->load(['category', 'owner:id,name', 'validator:id,name', 'latestVersion']));
    }

    public function restore(Request $request, Document $document): JsonResponse|DocumentResource
    {
        Gate::authorize('restore', $document);

        if (! $document->isArchived()) {
            return response()->json(['message' => 'Ce document n\'est pas archivé.'], 409);
        }

        $document->statut = Document::STATUT_ACTIF;
        $document->archived_at = null;
        $document->save();

        ActivityLogger::log($request->user(), ActivityLog::DOCUMENT_RESTORED, 'a restauré le document '.ActivityLogger::label($document).'.', $document);

        return new DocumentResource($document->load(['category', 'owner:id,name', 'validator:id,name', 'latestVersion']));
    }
}

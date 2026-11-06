<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVersionRequest;
use App\Http\Resources\VersionResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVersionController extends Controller
{
    public function __construct(private readonly DocumentVersionService $service)
    {
    }

    /** Historique complet des versions (de la plus récente à la plus ancienne). */
    public function index(Document $document)
    {
        Gate::authorize('view', $document);

        return VersionResource::collection($document->versions()->with('author:id,name')->get());
    }

    /** Ajoute une version sans toucher aux précédentes. */
    public function store(StoreVersionRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('addVersion', $document); // refuse : lecture seule, archivé, sans accès

        $version = $this->service->addVersion($document, $request->user(), $request->file('fichier'));

        // Le document change : on met à jour sa date de modification.
        $document->touch();

        return (new VersionResource($version->load('author:id,name')))->response()->setStatusCode(201);
    }

    /**
     * Téléchargement sécurisé : le fichier est dans un répertoire privé et
     * n'est envoyé (en flux binaire) qu'après vérification du droit de lecture.
     * La route utilise scopeBindings : la version doit appartenir au document.
     */
    public function download(Document $document, DocumentVersion $version): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $document);

        $disk = Storage::disk(DocumentVersionService::DISK);

        if (! $disk->exists($version->chemin_fichier)) {
            return response()->json(['message' => 'Fichier introuvable sur le serveur.'], 404);
        }

        return $disk->download($version->chemin_fichier, $version->nom_original, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

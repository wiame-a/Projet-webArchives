<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Circuit de validation : en_attente -> valide | rejete, et retour à « en attente ».
 * Réservé aux administrateurs et aux archivistes (voir DocumentPolicy).
 */
class DocumentValidationController extends Controller
{
    public function approve(Request $request, Document $document): JsonResponse|DocumentResource
    {
        Gate::authorize('validate', $document);

        if ($document->validation === Document::VALIDATION_VALIDE) {
            return response()->json(['message' => 'Ce document est déjà validé.'], 409);
        }

        return $this->transition($request, $document, Document::VALIDATION_VALIDE, null);
    }

    public function reject(RejectDocumentRequest $request, Document $document): JsonResponse|DocumentResource
    {
        Gate::authorize('reject', $document);

        if ($document->validation === Document::VALIDATION_REJETE) {
            return response()->json(['message' => 'Ce document est déjà rejeté.'], 409);
        }

        return $this->transition($request, $document, Document::VALIDATION_REJETE, $request->validated('motif'));
    }

    /** Remet le document « en attente » (correction d'une décision). */
    public function reopen(Request $request, Document $document): JsonResponse|DocumentResource
    {
        Gate::authorize('reopen', $document);

        if ($document->validation === Document::VALIDATION_EN_ATTENTE) {
            return response()->json(['message' => 'Ce document est déjà en attente de validation.'], 409);
        }

        $document->forceFill([
            'validation' => Document::VALIDATION_EN_ATTENTE,
            'motif_rejet' => null,
            'validated_by' => null,
            'validated_at' => null,
        ])->save();

        ActivityLogger::log($request->user(), ActivityLog::DOCUMENT_REOPENED, 'a remis le document '.ActivityLogger::label($document).' en attente de validation.', $document);

        return $this->respond($document);
    }

    private function transition(Request $request, Document $document, string $state, ?string $motif): DocumentResource
    {
        $document->forceFill([
            'validation' => $state,
            'motif_rejet' => $motif,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ])->save();

        $label = ActivityLogger::label($document);
        $approved = $state === Document::VALIDATION_VALIDE;
        ActivityLogger::log(
            $request->user(),
            $approved ? ActivityLog::DOCUMENT_VALIDATED : ActivityLog::DOCUMENT_REJECTED,
            $approved ? 'a validé le document '.$label.'.' : 'a rejeté le document '.$label.'. Motif : '.$motif,
            $document
        );

        return $this->respond($document);
    }

    private function respond(Document $document): DocumentResource
    {
        return new DocumentResource($document->load(['category', 'owner:id,name', 'validator:id,name', 'latestVersion']));
    }
}

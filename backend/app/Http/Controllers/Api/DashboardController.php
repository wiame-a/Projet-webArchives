<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $accessible = fn () => Document::query()->accessibleBy($user);

        $stats = [
            'documents_actifs' => $accessible()->where('statut', Document::STATUT_ACTIF)->count(),
            'documents_archives' => $accessible()->where('statut', Document::STATUT_ARCHIVE)->count(),
            'mes_documents' => Document::where('user_id', $user->id)->count(),
            'partages_avec_moi' => Document::where('user_id', '!=', $user->id)
                ->whereHas('permissions', fn ($p) => $p->where('user_id', $user->id))
                ->count(),
            'versions' => DocumentVersion::whereIn('document_id', $accessible()->select('documents.id'))->count(),
            'categories' => Category::count(),
        ];

        if ($user->isAdmin()) {
            $stats['utilisateurs'] = User::count();
        }

        $recents = $accessible()
            ->where('statut', Document::STATUT_ACTIF)
            ->with([
                'category',
                'owner:id,name',
                'latestVersion',
                'permissions' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->withCount('versions')
            ->latest('documents.created_at')
            ->latest('documents.id')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recents' => DocumentResource::collection($recents)->resolve($request),
        ]);
    }
}

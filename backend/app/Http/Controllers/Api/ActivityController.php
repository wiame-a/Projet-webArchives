<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    /**
     * Journal global : réservé aux administrateurs et aux archivistes.
     * Filtres : action, user_id, q (texte), date_debut, date_fin.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->isStaff(), 403, 'Le journal d\'activité est réservé aux administrateurs et aux archivistes.');

        $f = $request->validate([
            'action' => ['nullable', Rule::in(ActivityLog::ACTIONS)],
            'user_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $logs = ActivityLog::query()
            ->with(['user:id,name', 'document:id,code,titre'])
            ->when($f['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('description', 'like', '%'.$v.'%'))
            ->when($f['date_debut'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['date_fin'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($f['per_page'] ?? 20)
            ->withQueryString();

        return ActivityLogResource::collection($logs);
    }

    /** Historique d'un document : propriétaire, archivistes et administrateurs. */
    public function forDocument(Document $document)
    {
        Gate::authorize('activity', $document);

        return ActivityLogResource::collection(
            $document->activities()->with(['user:id,name'])->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get()
        );
    }
}

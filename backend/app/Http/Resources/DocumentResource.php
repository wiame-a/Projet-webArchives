<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'description' => $this->description,
            'mots_cles' => $this->mots_cles,
            'statut' => $this->statut,
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'latest_version' => new VersionResource($this->whenLoaded('latestVersion')),
            'versions' => VersionResource::collection($this->whenLoaded('versions')),
            'versions_count' => $this->whenCounted('versions'),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),

            // Droit de l'utilisateur courant + actions possibles : le frontend
            // s'en sert pour afficher ou masquer les boutons (la vraie sécurité
            // reste côté API via les Policies).
            'my_right' => $this->rightFor($user),
            'can' => [
                'update' => Gate::forUser($user)->allows('update', $this->resource),
                'delete' => Gate::forUser($user)->allows('delete', $this->resource),
                'share' => Gate::forUser($user)->allows('share', $this->resource),
                'archive' => Gate::forUser($user)->allows('archive', $this->resource),
                'restore' => Gate::forUser($user)->allows('restore', $this->resource),
                'add_version' => Gate::forUser($user)->allows('addVersion', $this->resource),
            ],
        ];
    }
}

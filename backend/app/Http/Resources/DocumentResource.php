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
            'code' => $this->code,
            'reference' => $this->reference,
            'titre' => $this->titre,
            'description' => $this->description,
            'mots_cles' => $this->mots_cles,
            'statut' => $this->statut,
            'validation' => $this->validation,
            // Statut à afficher dans le badge : en_attente | valide | rejete | archive
            'status' => $this->displayStatus(),
            'status_label' => $this->displayStatusLabel(),
            'motif_rejet' => $this->motif_rejet,
            'validated_at' => $this->validated_at,
            'validated_by' => $this->whenLoaded('validator', fn () => $this->validator
                ? ['id' => $this->validator->id, 'name' => $this->validator->name] : null),
            // Type et taille du fichier courant (dernière version), si elle est chargée.
            'file_type' => $this->whenLoaded('latestVersion', fn () => $this->latestVersion
                ? strtolower(pathinfo($this->latestVersion->nom_original, PATHINFO_EXTENSION)) : null),
            'file_size' => $this->whenLoaded('latestVersion', fn () => $this->latestVersion?->taille),
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
                'validate' => Gate::forUser($user)->allows('validate', $this->resource),
                'activity' => Gate::forUser($user)->allows('activity', $this->resource),
            ],
        ];
    }
}

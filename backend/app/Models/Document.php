<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    public const STATUT_ACTIF = 'actif';
    public const STATUT_ARCHIVE = 'archive';

    // Niveaux de droit retournés par rightFor()
    public const RIGHT_ADMIN = 'admin';
    public const RIGHT_OWNER = 'proprietaire';
    public const RIGHT_EDIT = 'modification';
    public const RIGHT_READ = 'lecture';

    /** statut, archived_at et user_id sont posés explicitement par le code. */
    protected $fillable = ['titre', 'description', 'mots_cles', 'category_id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('numero_version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('numero_version');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(DocumentPermission::class);
    }

    public function isArchived(): bool
    {
        return $this->statut === self::STATUT_ARCHIVE;
    }

    /**
     * Documents que l'utilisateur a le droit de voir :
     * l'administrateur voit tout, les autres voient leurs documents
     * et ceux qui leur ont été partagés.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('documents.user_id', $user->id)
                ->orWhereHas('permissions', fn (Builder $p) => $p->where('user_id', $user->id));
        });
    }

    /**
     * Niveau de droit de l'utilisateur sur ce document (null = aucun accès).
     * Utilise la relation `permissions` si elle est déjà chargée (évite le N+1).
     */
    public function rightFor(User $user): ?string
    {
        if ($user->isAdmin()) {
            return self::RIGHT_ADMIN;
        }

        if ($this->user_id === $user->id) {
            return self::RIGHT_OWNER;
        }

        $permission = $this->relationLoaded('permissions')
            ? $this->permissions->firstWhere('user_id', $user->id)
            : $this->permissions()->where('user_id', $user->id)->first();

        return $permission?->droit;
    }
}

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

    // Circuit de validation (colonne `validation`)
    public const VALIDATION_EN_ATTENTE = 'en_attente';
    public const VALIDATION_VALIDE = 'valide';
    public const VALIDATION_REJETE = 'rejete';

    public const VALIDATIONS = [
        self::VALIDATION_EN_ATTENTE,
        self::VALIDATION_VALIDE,
        self::VALIDATION_REJETE,
    ];

    /** Préfixe du code unique : ARC-0001, ARC-0002… */
    public const CODE_PREFIX = 'ARC-';

    // Niveaux de droit retournés par rightFor()
    public const RIGHT_ADMIN = 'admin';
    public const RIGHT_ARCHIVISTE = 'archiviste';
    public const RIGHT_OWNER = 'proprietaire';
    public const RIGHT_EDIT = 'modification';
    public const RIGHT_READ = 'lecture';

    /**
     * statut, validation, archived_at, code et user_id sont posés explicitement
     * par le code (jamais par une requête HTTP).
     */
    protected $fillable = ['titre', 'reference', 'description', 'mots_cles', 'category_id'];

    /** Valeur par défaut d'un nouveau document : actif, en attente de validation. */
    protected $attributes = [
        'statut' => self::STATUT_ACTIF,
        'validation' => self::VALIDATION_EN_ATTENTE,
    ];

    /**
     * Génère le code unique « ARC-XXXX » juste après l'insertion.
     * Il est dérivé de l'identifiant auto-incrémenté : aucune collision possible,
     * même avec deux créations simultanées (contrairement à « dernier code + 1 »).
     */
    protected static function booted(): void
    {
        static::created(function (Document $document) {
            if ($document->code === null) {
                $document->forceFill(['code' => self::codeFor($document->getKey())])->saveQuietly();
            }
        });
    }

    public static function codeFor(int $id): string
    {
        return sprintf('%s%04d', self::CODE_PREFIX, $id);
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'validated_at' => 'datetime'];
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

    /** Utilisateur (admin / archiviste) qui a validé ou rejeté le document. */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
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
     * Statut affiché (badge) : « archive » l'emporte, sinon l'état de validation.
     * Valeurs : en_attente | valide | rejete | archive.
     */
    public function displayStatus(): string
    {
        return $this->isArchived() ? self::STATUT_ARCHIVE : $this->validation;
    }

    public function displayStatusLabel(): string
    {
        return match ($this->displayStatus()) {
            self::STATUT_ARCHIVE => 'Archivé',
            self::VALIDATION_VALIDE => 'Validé',
            self::VALIDATION_REJETE => 'Rejeté',
            default => 'En attente',
        };
    }

    /**
     * Documents que l'utilisateur a le droit de voir :
     * l'administrateur voit tout, les autres voient leurs documents
     * et ceux qui leur ont été partagés.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        // Administrateurs et archivistes voient tous les documents.
        if ($user->isStaff()) {
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

        if ($user->isArchiviste()) {
            return self::RIGHT_ARCHIVISTE;
        }

        $permission = $this->relationLoaded('permissions')
            ? $this->permissions->firstWhere('user_id', $user->id)
            : $this->permissions()->where('user_id', $user->id)->first();

        return $permission?->droit;
    }
}

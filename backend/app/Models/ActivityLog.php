<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne du journal d'activité (lecture seule une fois écrite : pas de updated_at). */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    // Actions enregistrées
    public const DOCUMENT_CREATED = 'document.created';
    public const DOCUMENT_UPDATED = 'document.updated';
    public const DOCUMENT_DELETED = 'document.deleted';
    public const DOCUMENT_ARCHIVED = 'document.archived';
    public const DOCUMENT_RESTORED = 'document.restored';
    public const DOCUMENT_VALIDATED = 'document.validated';
    public const DOCUMENT_REJECTED = 'document.rejected';
    public const DOCUMENT_REOPENED = 'document.reopened';
    public const VERSION_ADDED = 'version.added';
    public const VERSION_DOWNLOADED = 'version.downloaded';
    public const SHARE_GRANTED = 'share.granted';
    public const SHARE_REVOKED = 'share.revoked';
    public const PASSWORD_CHANGED = 'account.password_changed';
    public const PROFILE_UPDATED = 'account.profile_updated';

    public const ACTIONS = [
        self::DOCUMENT_CREATED, self::DOCUMENT_UPDATED, self::DOCUMENT_DELETED,
        self::DOCUMENT_ARCHIVED, self::DOCUMENT_RESTORED,
        self::DOCUMENT_VALIDATED, self::DOCUMENT_REJECTED, self::DOCUMENT_REOPENED,
        self::VERSION_ADDED, self::VERSION_DOWNLOADED,
        self::SHARE_GRANTED, self::SHARE_REVOKED,
        self::PASSWORD_CHANGED, self::PROFILE_UPDATED,
    ];

    protected $fillable = ['user_id', 'document_id', 'action', 'description', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

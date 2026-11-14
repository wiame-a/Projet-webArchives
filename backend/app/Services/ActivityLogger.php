<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\User;

/**
 * Point d'entrée unique pour écrire dans le journal d'activité.
 * Les contrôleurs appellent ActivityLogger::log(...) après chaque action importante.
 */
class ActivityLogger
{
    public static function log(?User $user, string $action, string $description, ?Document $document = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $user?->id,
            'document_id' => $document?->id,
            'action' => $action,
            'description' => mb_substr($description, 0, 255),
        ]);
    }

    /** « « Titre » (ARC-0001) » : désignation courte d'un document dans une phrase. */
    public static function label(Document $document): string
    {
        return '« '.$document->titre.' »'.($document->code ? ' ('.$document->code.')' : '');
    }
}

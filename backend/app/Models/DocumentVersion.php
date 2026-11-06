<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id', 'user_id', 'numero_version',
        'chemin_fichier', 'nom_original', 'taille',
    ];

    /** Le chemin interne du fichier n'est jamais exposé en JSON. */
    protected $hidden = ['chemin_fichier'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

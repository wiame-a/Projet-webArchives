<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['nom', 'code', 'description', 'couleur'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}

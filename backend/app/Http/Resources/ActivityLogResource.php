<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            // null si le document a été supprimé depuis
            'document' => $this->whenLoaded('document', fn () => $this->document
                ? ['id' => $this->document->id, 'code' => $this->document->code, 'titre' => $this->document->titre] : null),
        ];
    }
}

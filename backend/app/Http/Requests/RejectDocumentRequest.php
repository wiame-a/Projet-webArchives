<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Le motif est obligatoire : l'auteur doit savoir quoi corriger. */
class RejectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'autorisation est faite par la Policy dans le contrôleur
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif du rejet est obligatoire.',
            'motif.min' => 'Le motif doit contenir au moins 5 caractères.',
            'motif.max' => 'Le motif ne doit pas dépasser 500 caractères.',
        ];
    }
}

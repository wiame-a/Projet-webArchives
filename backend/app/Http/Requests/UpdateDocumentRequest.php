<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Modification des métadonnées (le fichier se change via une nouvelle version). */
class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // DocumentPolicy::update est appliquée dans le contrôleur
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mots_cles' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre est obligatoire.',
            'category_id.required' => 'Choisissez une catégorie.',
            'category_id.exists' => 'Cette catégorie n\'existe pas.',
        ];
    }
}

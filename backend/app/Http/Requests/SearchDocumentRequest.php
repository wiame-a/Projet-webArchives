<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtres de la liste / recherche avancée (tous optionnels, combinables). */
class SearchDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],           // texte libre : code, référence, titre, description, mots-clés
            'titre' => ['nullable', 'string', 'max:255'],
            'mots_cles' => ['nullable', 'string', 'max:255'],   // plusieurs mots séparés par des virgules (ET)
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'], // auteur
            'statut' => ['nullable', Rule::in([Document::STATUT_ACTIF, Document::STATUT_ARCHIVE])],
            'validation' => ['nullable', Rule::in(Document::VALIDATIONS)],
            'portee' => ['nullable', Rule::in(['mes', 'partages'])],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'tri' => ['nullable', Rule::in(['created_at', 'updated_at', 'titre'])],
            'ordre' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'date_debut.date' => 'La date de début n\'est pas valide.',
            'date_fin.date' => 'La date de fin n\'est pas valide.',
        ];
    }
}

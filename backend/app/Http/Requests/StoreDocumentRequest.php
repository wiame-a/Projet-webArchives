<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /** Extensions et types MIME autorisés (PDF, DOCX, XLSX, images). */
    public const EXTENSIONS = 'pdf,docx,xlsx,jpg,jpeg,png,gif,webp';

    public const MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', // certaines versions de libmagic détectent ainsi les .docx/.xlsx
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    ];

    /** 10 Mo, exprimés en Ko pour la règle "max". */
    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true; // tout utilisateur connecté peut créer un document
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mots_cles' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'fichier' => [
                'required', 'file', 'max:'.self::MAX_KB,
                'extensions:'.self::EXTENSIONS,
                'mimetypes:'.implode(',', self::MIME_TYPES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre est obligatoire.',
            'category_id.required' => 'Choisissez une catégorie.',
            'category_id.exists' => 'Cette catégorie n\'existe pas.',
            'fichier.required' => 'Le fichier est obligatoire.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            'fichier.extensions' => 'Formats autorisés : PDF, DOCX, XLSX et images (JPG, PNG, GIF, WEBP).',
            'fichier.mimetypes' => 'Le contenu du fichier ne correspond pas à un format autorisé.',
            'fichier.uploaded' => 'Le fichier n\'a pas pu être envoyé (taille maximale : 10 Mo).',
        ];
    }
}

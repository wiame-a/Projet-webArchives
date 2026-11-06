<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // DocumentPolicy::addVersion est appliquée dans le contrôleur
    }

    public function rules(): array
    {
        return [
            'fichier' => [
                'required', 'file', 'max:'.StoreDocumentRequest::MAX_KB,
                'extensions:'.StoreDocumentRequest::EXTENSIONS,
                'mimetypes:'.implode(',', StoreDocumentRequest::MIME_TYPES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'fichier.required' => 'Le fichier est obligatoire.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            'fichier.extensions' => 'Formats autorisés : PDF, DOCX, XLSX et images (JPG, PNG, GIF, WEBP).',
            'fichier.mimetypes' => 'Le contenu du fichier ne correspond pas à un format autorisé.',
            'fichier.uploaded' => 'Le fichier n\'a pas pu être envoyé (taille maximale : 10 Mo).',
        ];
    }
}

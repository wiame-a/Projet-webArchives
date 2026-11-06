<?php

namespace App\Http\Requests;

use App\Models\DocumentPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // DocumentPolicy::share est appliquée dans le contrôleur
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'droit' => ['required', Rule::in([DocumentPermission::LECTURE, DocumentPermission::MODIFICATION])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Choisissez un utilisateur.',
            'user_id.exists' => 'Cet utilisateur n\'existe pas.',
            'droit.required' => 'Choisissez un droit.',
            'droit.in' => 'Le droit doit être "lecture" ou "modification".',
        ];
    }
}

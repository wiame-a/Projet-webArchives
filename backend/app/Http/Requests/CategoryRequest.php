<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sert à la création et à la modification d'une catégorie. */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'autorisation est faite par CategoryPolicy dans le contrôleur
    }

    public function rules(): array
    {
        return [
            'nom' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'nom')->ignore($this->route('category')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la catégorie est obligatoire.',
            'nom.unique' => 'Une catégorie portant ce nom existe déjà.',
        ];
    }
}

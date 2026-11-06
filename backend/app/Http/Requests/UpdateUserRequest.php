<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Administration : changement de rôle d'un compte. */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // UserPolicy::update est appliquée dans le contrôleur
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
        ];
    }

    public function messages(): array
    {
        return ['role.in' => 'Le rôle doit être "admin" ou "user".'];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-router') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('adminUser');
        $creating = ! $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->getKey())],
            'role' => ['required', Rule::enum(AdminRole::class)],
            'status' => ['required', Rule::enum(AdminStatus::class)],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::min(12)->letters()->numbers(), 'confirmed'],
        ];
    }
}

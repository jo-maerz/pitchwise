<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
            'organization_id' => ['nullable', 'integer', Rule::exists(Organization::class, 'id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('role') === Role::OrgAdmin->value && ! $this->filled('organization_id')) {
                $validator->errors()->add('role', 'An organization admin needs an organization.');
            }
            // Keeps at least one admin: nobody can take away their own admin rights.
            if ($this->route('user')->is($this->user()) && $this->input('role') !== Role::Admin->value) {
                $validator->errors()->add('role', 'You cannot remove your own admin role.');
            }
        }];
    }
}

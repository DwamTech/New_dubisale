<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:db,files,full'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => __('api.backup_type_required'),
            'type.in'       => __('api.backup_type_invalid'),
        ];
    }
}

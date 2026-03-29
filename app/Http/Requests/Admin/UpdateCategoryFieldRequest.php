<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('categoryField')?->id ?? null;

        return [
            'category_slug'   => ['sometimes', 'string', 'max:100'],
            'field_name'      => ['required', 'string', 'max:100'],
            'display_name'    => ['sometimes', 'string', 'max:150'],
            'display_name_en' => ['nullable', 'string', 'max:150'],
            'type'            => ['sometimes', 'string', 'in:string,int,decimal,bool,date,json'],
            'options'         => ['required', 'array'],
            'options_en'      => ['nullable', 'array'],
            'required'        => ['sometimes', 'boolean'],
            'filterable'      => ['sometimes', 'boolean'],
            'rules_json'      => ['nullable', 'array'],
            'sort_order'      => ['nullable', 'integer'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['options', 'options_en'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $decoded = json_decode($data[$key], true);
                $data[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : [$data[$key]];
            }
        }

        $this->replace($data);
    }
}

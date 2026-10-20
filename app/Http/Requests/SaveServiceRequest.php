<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'required', 'string', 'max:150'],
            'summary' => [$presence, 'required', 'string', 'max:300'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'features' => ['sometimes', 'nullable', 'array', 'max:20'],
            'features.*' => ['string', 'max:150'],
            'starting_price_huf' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999999'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}

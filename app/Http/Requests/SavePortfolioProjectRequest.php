<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePortfolioProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'required', 'string', 'max:160'],
            'category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'summary' => [$presence, 'required', 'string', 'max:400'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'technologies' => ['sometimes', 'nullable', 'array', 'max:30'],
            'technologies.*' => ['string', 'max:80'],
            'project_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'cover_image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover_image' => ['sometimes', 'boolean'],
            'is_demo' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'website_type' => ['required', Rule::in(['landing', 'business', 'webshop', 'custom'])],
            'design_style' => ['nullable', Rule::in(['dark-premium', 'elegant-minimal', 'modern-creative', 'mixed', 'undecided'])],
            'colors' => ['nullable', 'array', 'max:6'],
            'colors.*' => ['required', 'string', 'max:40'],
            'features' => ['nullable', 'array', 'max:15'],
            'features.*' => ['required', 'string', 'max:80'],
            'inspiration_urls' => ['nullable', 'array', 'max:5'],
            'inspiration_urls.*' => ['required', 'url:http,https', 'max:500'],
            'budget_range' => ['nullable', 'string', 'max:60'],
            'desired_deadline' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'project_description' => ['required', 'string', 'min:10', 'max:5000'],
            'wants_maintenance' => ['sometimes', 'boolean'],
            'privacy_notice_acknowledged' => ['required', 'accepted'],
            'website' => ['nullable', 'max:0'], // hidden honeypot; real clients leave empty
        ];
    }
}

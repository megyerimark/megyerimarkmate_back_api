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
            'industry' => ['nullable', Rule::in(['Szolgáltatás', 'Egészség és szépség', 'Vendéglátás', 'Technológia', 'Kereskedelem', 'Más terület'])],
            'layout_style' => ['nullable', Rule::in(['editorial', 'split', 'centered'])],
            'font_style' => ['nullable', Rule::in(['elegant', 'modern'])],
            'colors' => ['nullable', 'array', 'max:6'],
            'colors.*' => ['required', 'string', 'max:40'],
            'features' => ['nullable', 'array', 'max:15'],
            'features.*' => ['required', 'string', 'max:80'],
            'inspiration_urls' => ['nullable', 'array', 'max:5'],
            'inspiration_urls.*' => ['required', 'url:http,https', 'max:500'],
            'budget_range' => ['nullable', 'string', 'max:60'],
            'consultation_preference' => ['nullable', Rule::in(['email', 'phone', 'online'])],
            'desired_deadline' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'project_description' => ['required', 'string', 'min:10', 'max:5000'],
            'wants_maintenance' => ['sometimes', 'boolean'],
            'privacy_notice_acknowledged' => ['required', 'accepted'],
            'website' => ['nullable', 'max:0'], // hidden honeypot; real clients leave empty
            // Érkezési forrás: nem megbízható, kizárólag statisztikára használjuk.
            'source' => ['nullable', Rule::in(['direct', 'search', 'social', 'referral', 'email', 'campaign'])],
            'utm_source' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN _.+\-]+$/u'],
            'utm_medium' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN _.+\-]+$/u'],
            'utm_campaign' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN _.+\-]+$/u'],
            'referrer_host' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9.\-]+$/i'],
            'landing_path' => ['nullable', 'string', 'max:160', 'regex:/^\/[A-Za-z0-9\-._~\/]*$/'],
        ];
    }
}

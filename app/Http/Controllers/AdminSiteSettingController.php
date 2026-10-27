<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSiteSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SiteSetting::allPublic()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_title' => ['sometimes', 'required', 'string', 'max:140'],
            'hero_headline' => ['sometimes', 'required', 'string', 'max:250'],
            'hero_description' => ['sometimes', 'required', 'string', 'max:800'],
            'meta_title' => ['sometimes', 'required', 'string', 'max:70'],
            'meta_description' => ['sometimes', 'required', 'string', 'max:180'],
            'og_image_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'instagram_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'linkedin_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'github_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'reviews_widget_id' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/\A[A-Za-z0-9]+\z/'],
            'google_review_url' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'promo_enabled' => ['sometimes', 'boolean'],
            'promo_percent' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'promo_title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'promo_title_en' => ['sometimes', 'nullable', 'string', 'max:80'],
            'promo_text' => ['sometimes', 'nullable', 'string', 'max:240'],
            'promo_text_en' => ['sometimes', 'nullable', 'string', 'max:240'],
            'promo_ends_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        if (array_key_exists('promo_enabled', $data)) $data['promo_enabled'] = $data['promo_enabled'] ? '1' : '0';
        if (($data['promo_enabled'] ?? null) === '1' && (int) ($data['promo_percent'] ?? SiteSetting::allPublic()['promo_percent']) < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['promo_percent' => 'Az akció bekapcsolásához adj meg legalább 1% kedvezményt.']);
        }

        foreach ($data as $key => $value) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }
        \App\Support\PublicCache::flush();
        AdminAudit::record($request, 'updated', 'site_settings');
        return response()->json(['data' => SiteSetting::allPublic()]);
    }
}

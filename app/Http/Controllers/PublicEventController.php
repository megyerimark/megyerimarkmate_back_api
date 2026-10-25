<?php

namespace App\Http\Controllers;

use App\Models\SiteEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Névtelen eseményszámláló. Nem tárol IP-címet, user-agentet vagy azonosítót,
 * és nem állít be sütit – így a látogatók nem követhetők vele.
 */
class PublicEventController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'event' => ['required', 'string', Rule::in(SiteEvent::EVENTS)],
            'path' => ['nullable', 'string', 'max:160', 'regex:/^\/[A-Za-z0-9\-._~\/]*$/'],
            'source' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN _.+\-]+$/u'],
        ]);
        // Robotok és hibakereső böngészők ne torzítsák a statisztikát.
        if (preg_match('/bot|crawl|spider|slurp|headless|lighthouse|preview/i', (string) $request->userAgent())) {
            return response()->noContent();
        }
        SiteEvent::create($data);
        return response()->noContent();
    }
}

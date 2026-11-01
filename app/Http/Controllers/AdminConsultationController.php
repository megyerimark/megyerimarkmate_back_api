<?php

namespace App\Http\Controllers;

use App\Models\ConsultationSlot;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminConsultationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ConsultationSlot::query()->with('booking')
            ->where('starts_at', '>=', now()->subDays(14))->orderBy('starts_at')->limit(200)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', Rule::in([30, 45, 60])],
        ]);
        $date = \Carbon\Carbon::parse($data['starts_at'])->utc();
        if ($date->lessThan(now()->addHours(2)) || $date->greaterThan(now()->addDays(90))) {
            throw ValidationException::withMessages(['starts_at' => 'Válassz 2 óránál későbbi, de 90 napon belüli időpontot.']);
        }
        // Validate the entire time interval, not just identical start times.
        $nearby = ConsultationSlot::whereBetween('starts_at', [
            $date->copy()->subMinutes(60), $date->copy()->addMinutes($data['duration_minutes'])
        ])->get();
        foreach ($nearby as $existing) {
            if ($existing->starts_at->lessThan($date->copy()->addMinutes($data['duration_minutes'])) &&
                $existing->starts_at->copy()->addMinutes($existing->duration_minutes)->greaterThan($date)) {
                throw ValidationException::withMessages(['starts_at' => 'Ez az időpont átfed egy másik konzultációval.']);
            }
        }
        $slot = ConsultationSlot::create(['starts_at' => $date, 'duration_minutes' => $data['duration_minutes']]);
        AdminAudit::record($request, 'consultation_slot_created', 'consultation_slot', $slot->id);
        return response()->json(['data' => $slot], 201);
    }

    public function destroy(Request $request, ConsultationSlot $consultationSlot): JsonResponse
    {
        if ($consultationSlot->booking()->exists()) {
            throw ValidationException::withMessages(['slot' => 'Lefoglalt konzultációs időpontot nem lehet törölni; egyeztess előbb az ügyféllel.']);
        }
        $consultationSlot->delete();
        AdminAudit::record($request, 'consultation_slot_deleted', 'consultation_slot', $consultationSlot->id);
        return response()->json(['message' => 'Időpont törölve.']);
    }

    /** Konzultációs beállítások (privát): állandó videóhívás-link. */
    public function settings(): JsonResponse
    {
        return response()->json(['data' => \App\Models\AdminSetting::consultation()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['meeting_url' => ['nullable', 'url:https', 'max:500']]);
        \App\Models\AdminSetting::updateOrCreate(['key' => 'consultation'], ['value' => ['meeting_url' => $data['meeting_url'] ?? null]]);
        AdminAudit::record($request, 'consultation_settings_updated', 'admin_setting', null);
        return response()->json(['data' => \App\Models\AdminSetting::consultation(), 'message' => 'Mentve.']);
    }
}

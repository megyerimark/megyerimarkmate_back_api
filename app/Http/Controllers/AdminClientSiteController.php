<?php

namespace App\Http\Controllers;

use App\Models\AdminSetting;
use App\Models\ClientSite;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kész projektek + zero-knowledge hozzáférés-széf.
 * A `vault_blob` mezőket a böngésző titkosítja a széfjelszóból származtatott kulccsal (PBKDF2 → AES-GCM);
 * a szerver csak a sót, az iterációszámot és egy ellenőrző titkosított mintát tárol, magát a jelszót soha.
 */
class AdminClientSiteController extends Controller
{
    private const BLOB_RULE = ['nullable', 'string', 'max:200000', 'regex:/\A\{"v":1,"iv":"[A-Za-z0-9+\/=]{16,32}","ct":"[A-Za-z0-9+\/=]+"\}\z/'];

    public function index(): JsonResponse
    {
        return response()->json(['data' => ClientSite::orderByRaw('launched_on IS NULL')->orderByDesc('launched_on')->orderByDesc('id')->get()])
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $site = new ClientSite($request->validate($this->rules(true)));
        $site->vault_blob = $request->input('vault_blob');
        $site->save();
        AdminAudit::record($request, 'created', 'client_site', $site->id);
        return response()->json(['data' => $site], 201);
    }

    public function update(Request $request, ClientSite $clientSite): JsonResponse
    {
        $clientSite->fill($request->validate($this->rules(false)));
        if ($request->exists('vault_blob')) $clientSite->vault_blob = $request->input('vault_blob');
        $clientSite->save();
        AdminAudit::record($request, 'updated', 'client_site', $clientSite->id);
        return response()->json(['data' => $clientSite]);
    }

    public function destroy(Request $request, ClientSite $clientSite): JsonResponse
    {
        $id = $clientSite->id;
        $clientSite->delete();
        AdminAudit::record($request, 'deleted', 'client_site', $id);
        return response()->json(['message' => 'Projekt törölve.']);
    }

    /** A széf beállításai (só, iteráció, ellenőrző minta). Jelszót és kulcsot nem tartalmaz. */
    public function vault(): JsonResponse
    {
        return response()->json(['data' => AdminSetting::find('vault')?->value])->header('Cache-Control', 'no-store');
    }

    /**
     * Széf létrehozása vagy jelszócsere. Jelszócserénél a böngésző az összes blobot újratitkosítja,
     * és egyetlen tranzakcióban küldi vissza – így nem maradhat vegyes kulccsal titkosított adat.
     */
    public function saveVault(Request $request): JsonResponse
    {
        $data = $request->validate([
            'salt' => ['required', 'string', 'regex:/\A[A-Za-z0-9+\/=]{20,64}\z/'],
            'iterations' => ['required', 'integer', 'min:310000', 'max:5000000'],
            'verifier' => ['required', 'string', 'max:500', ...array_slice(self::BLOB_RULE, 2)],
            'blobs' => ['sometimes', 'array'],
            'blobs.*.id' => ['required', 'integer', 'exists:client_sites,id'],
            'blobs.*.vault_blob' => self::BLOB_RULE,
        ]);
        $existing = AdminSetting::find('vault');
        $withBlobs = ClientSite::whereNotNull('vault_blob')->pluck('id')->sort()->values()->all();
        $sent = collect($data['blobs'] ?? [])->pluck('id')->sort()->values()->all();
        if ($existing && $withBlobs !== array_values(array_intersect($sent, $withBlobs))) {
            throw ValidationException::withMessages(['blobs' => 'Jelszócseréhez minden titkosított bejegyzést újra kell titkosítani.']);
        }
        DB::transaction(function () use ($data) {
            AdminSetting::updateOrCreate(['key' => 'vault'], ['value' => [
                'salt' => $data['salt'], 'iterations' => $data['iterations'], 'verifier' => $data['verifier'], 'kdf' => 'PBKDF2-SHA256', 'cipher' => 'AES-256-GCM',
            ]]);
            foreach ($data['blobs'] ?? [] as $row) {
                ClientSite::whereKey($row['id'])->update(['vault_blob' => $row['vault_blob']]);
            }
        });
        AdminAudit::record($request, $existing ? 'vault_password_changed' : 'vault_created', 'vault');
        return response()->json(['message' => $existing ? 'Széfjelszó módosítva.' : 'Széf létrehozva.']);
    }

    private function rules(bool $create): array
    {
        $s = $create ? 'required' : 'sometimes';
        return [
            'name' => [$s, 'string', 'min:2', 'max:120'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:190', 'regex:/\A[a-z0-9.\-]+\.[a-z]{2,}\z/i'],
            'quote_request_id' => ['sometimes', 'nullable', 'integer', 'exists:quote_requests,id'],
            'client_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'client_email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'launched_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hosting_provider' => ['sometimes', 'nullable', 'string', 'max:120'],
            'domain_registrar' => ['sometimes', 'nullable', 'string', 'max:120'],
            'domain_expires_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hosting_expires_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'ssl_expires_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'tech_stack' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'vault_blob' => self::BLOB_RULE,
        ];
    }
}

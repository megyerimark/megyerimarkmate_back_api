<?php

namespace App\Support;

use App\Models\AdminActivity;
use Illuminate\Http\Request;

class AdminAudit
{
    public static function record(Request $request, string $action, string $entityType, ?int $entityId = null): void
    {
        AdminActivity::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }
}

<?php

namespace App\Traits;

use App\Models\AuditLog;

trait AuditLoggable
{
    public function logAudit($action, $old = null, $new = null)
    {
        $request = request();
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'path' => $request->path(),
            'platform' => $request->header('X-Platform', 'test'),
            'status_code' => 200,
        ]);
    }
}

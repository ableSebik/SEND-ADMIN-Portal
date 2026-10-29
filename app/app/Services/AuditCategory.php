<?php

namespace App\Services;

use App\Models\AdminRecord;

class AuditCategory
{
    public static function resolve(string $action, string $subject): string
    {
        if (str_starts_with($subject, 'Asset import #')) {
            return 'asset';
        }
        if (in_array($action, ['Signed in', 'Signed out'], true)) {
            return 'account';
        }
        if (str_starts_with($subject, 'User #') || in_array($action, ['Initial administrator created', 'Changed password', 'Password reset'], true)) {
            return 'user';
        }
        $module = in_array($subject, ['vehicles', 'trips', 'assets', 'vendors'], true) ? $subject : AdminRecord::withTrashed()->where('reference', $subject)->value('module');

        return match ($module) {
            'vehicles' => 'vehicle','trips' => 'trip','assets' => 'asset','vendors' => 'vendor',default => 'system'
        };
    }
}

<?php

namespace App\Services;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Access
{
    public static function allows(User $u, string $action): bool
    {
        if (! $u->active) {
            return false;
        }
        if ($u->isSuperAdmin()) {
            return true;
        }
        $grants = ['admin' => ['view', 'create', 'approve', 'audit', 'users', 'export', 'vehicles.write'], 'officer' => ['view', 'create', 'export'], 'driver' => ['view', 'trip']];

        return in_array($action, $grants[$u->role] ?? [], true);
    }

    public static function scope(Builder $q, User $u): Builder
    {
        if (! self::allows($u, 'view')) {
            return $q->whereRaw('1=0');
        }
        if ($u->isSuperAdmin()) {
            return $q;
        }
        if ($u->role === 'driver') {
            $q->where(fn ($q) => $q->where('module', 'vehicles')->orWhere(fn ($q) => $q->where('module', 'trips')->where('data->driver_id', (string) $u->id)));
        }

        return $q;
    }

    public static function canWriteRecord(User $user, string $module): bool
    {
        return self::allows($user, $module === 'vehicles' ? 'vehicles.write' : 'create');
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        return $actor->active && ($actor->isSuperAdmin() || ($actor->role === 'admin' && in_array($target->role, ['driver', 'officer'], true)));
    }

    public static function canAddTripNote(User $user, AdminRecord $trip): bool
    {
        return $user->active && $trip->module === 'trips' && ($user->isSuperAdmin() || ($user->role === 'driver' && (string) ($trip->data['driver_id'] ?? '') === (string) $user->id));
    }

    public static function canUploadRecord(User $user, AdminRecord $record): bool
    {
        return $record->module === 'trips' ? (self::allows($user, 'create') || self::canAddTripNote($user, $record)) : self::canWriteRecord($user, $record->module);
    }
}

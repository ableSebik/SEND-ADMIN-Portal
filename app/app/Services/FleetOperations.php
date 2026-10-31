<?php

namespace App\Services;

use App\Models\AdminRecord;
use Illuminate\Support\Facades\DB;

class FleetOperations
{
    public const KINDS = ['service' => 'Maintenance / servicing', 'roadworthiness' => 'Roadworthiness', 'insurance' => 'Insurance'];
    public const STATUSES = ['Available', 'Maintenance', 'Unavailable'];

    public static function event(AdminRecord $vehicle, string $kind, string $details, int $actor, ?int $trip = null): void
    {
        DB::table('vehicle_events')->insert(['vehicle_id' => $vehicle->id, 'trip_id' => $trip,
            'kind' => $kind, 'details' => $details, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function setStatus(AdminRecord $vehicle, string $status, string $reason, int $actor): void
    {
        if ($status === $vehicle->status) {
            return;
        }
        $before = $vehicle->status;
        $vehicle->update(['status' => $status, 'updated_by' => $actor, 'version' => $vehicle->version + 1]);
        self::event($vehicle, 'Status change', "$before → $status: $reason", $actor);
    }
}

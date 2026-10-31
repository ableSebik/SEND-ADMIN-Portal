<?php

namespace Database\Seeders;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoFleetSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'Demo fleet data is only available locally.');
        $admin = User::where('role', 'super_admin')->where('active', true)->firstOrFail();

        DB::transaction(function () use ($admin) {
            $drivers = collect([
                ['Demo OSO — Ama Mensah', 'ama', 'Accra'],
                ['Demo OSO — Kofi Asante', 'kofi', 'Accra'],
                ['Demo OSO — Amina Yakubu', 'amina', 'Tamale'],
                ['Demo OSO — Ibrahim Fuseini', 'ibrahim', 'Tamale'],
            ])->map(fn ($details) => User::firstOrCreate(
                ['email' => 'demo.'.$details[1].'@example.test'],
                ['name' => $details[0], 'password' => Str::random(48), 'role' => 'driver',
                    'department' => 'HR/Admin', 'location' => $details[2], 'active' => true]
            ));

            $fleet = [
                ['Toyota Hilux', 'DEMO-001', 'Accra', 0, 24500, 'Tema', 'Community outreach'],
                ['Toyota Land Cruiser', 'DEMO-002', 'Accra', 1, 38200, 'Koforidua', 'Project monitoring'],
                ['Nissan Navara', 'DEMO-003', 'Accra', 0, 18600, 'Winneba', 'Partner meeting'],
                ['Toyota Hilux', 'DEMO-004', 'Tamale', 2, 31200, 'Savelugu', 'Field supervision'],
                ['Mitsubishi Pajero', 'DEMO-005', 'Tamale', 3, 42700, 'Yendi', 'Community engagement'],
                ['Ford Ranger', 'DEMO-006', 'Tamale', 2, 15900, 'Bolgatanga', 'Programme review'],
            ];

            foreach ($fleet as $index => [$name, $registration, $office, $driverIndex, $opening, $destination, $purpose]) {
                $driver = $drivers[$driverIndex];
                $distance = [64, 168, 132, 52, 194, 322][$index];
                $vehicle = AdminRecord::firstOrCreate(['reference' => 'VEH-DEMO-'.($index + 1)], [
                    'module' => 'vehicles', 'name' => $name.' (Demo)', 'category' => '',
                    'department' => '', 'location' => $office, 'status' => 'Available',
                    'unique_key' => 'vehicle:'.$registration,
                    'data' => ['name' => $name.' (Demo)', 'registration' => $registration,
                        'location' => $office, 'serial' => 'DEMO-CHASSIS-'.($index + 1),
                        'driver_id' => (string) $driver->id, 'odometer' => $opening + $distance,
                        'notes' => 'Dummy vehicle for local demonstrations.'],
                    'created_by' => $admin->id, 'updated_by' => $admin->id,
                ]);

                for ($phase = 0; $phase < 2; $phase++) {
                    // Only vehicles 2 and 5 have pending trips, each with its own OSO.
                    $pending = $phase === 1 && in_array($index, [1, 4], true);
                    $start = $phase === 0 ? $opening : $opening + $distance;
                    $date = now()->startOfDay()->subDays($phase === 0 ? 14 - $index : 7 - $index)->setTime(8, 0);
                    $data = [
                        'name' => $purpose.' (Demo)', 'category' => 'Programs',
                        'from' => $office, 'destination' => $destination,
                        'vehicle_id' => (string) $vehicle->id, 'driver_id' => (string) $driver->id,
                        'opened_by' => (string) $admin->id, 'departure' => $date->format('Y-m-d\TH:i'),
                        'opening' => $start, 'fuel' => 30 + $index * 5, 'passengers' => 2 + $index % 4,
                        'notes' => 'Dummy trip for local demonstrations.',
                    ];
                    if (! $pending) {
                        $data += ['closing' => $start + $distance, 'distance' => $distance,
                            'return_at' => $date->copy()->addHours(7)->format('Y-m-d\TH:i'),
                            'closed_by' => (string) $admin->id, 'closed_at' => $date->copy()->addHours(7)->toIso8601String()];
                    }
                    $reference = 'TRI-DEMO-'.($index + 1).'-'.($phase + 1);
                    if (! AdminRecord::withTrashed()->where('reference', $reference)->exists()) {
                        AdminRecord::create([
                            'reference' => $reference, 'module' => 'trips', 'name' => $data['name'],
                            'category' => $data['category'], 'department' => $driver->department,
                            'location' => $office, 'status' => $pending ? 'Pending' : 'Completed',
                            'data' => $data, 'created_by' => $admin->id, 'updated_by' => $admin->id,
                        ]);
                        if (! $pending) {
                            $vehicle->update(['data' => [...$vehicle->data,
                                'odometer' => max($vehicle->data['odometer'], $data['closing'])]]);
                        }
                    }
                }
            }
        });
        $this->command?->info('Demo fleet ready: 6 vehicles, 12 trips (10 completed, 2 pending), and 4 OSO accounts.');
    }
}

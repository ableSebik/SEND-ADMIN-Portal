<?php

namespace App\Http\Controllers;

use App\Models\AdminRecord;
use App\Models\User;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    public function create(Request $request)
    {
        abort_unless(Access::allows($request->user(), 'trip'), 403);
        $vehicles = Access::scope(AdminRecord::where('module', 'vehicles'), $request->user())
            ->when($request->user()->role === 'driver', fn ($query) => $query->whereRaw('LOWER(TRIM(location)) = ?', [strtolower(trim($request->user()->location))])->where('location', '!=', ''))
            ->where('status', 'Available')->orderBy('name')->get();

        $drivers = $request->user()->isSuperAdmin() ? User::where('active', true)->where('role', 'driver')->orderBy('name')->get() : collect();

        return view('trips.open', compact('vehicles', 'drivers'));
    }

    public function store(Request $request)
    {
        abort_unless(Access::allows($request->user(), 'trip'), 403);
        $validated = $request->validate([
            'driver_id' => $request->user()->isSuperAdmin() ? 'required|integer|exists:users,id' : 'exclude',
            'name' => 'required|string|max:180',
            'category' => 'required|string|max:80',
            'from' => 'required|string|max:180',
            'destination' => 'required|string|max:180',
            'vehicle_id' => 'required|integer',
            'departure' => 'required|date_format:Y-m-d\TH:i',
            'opening' => 'required|numeric|min:0|max:999999999.9',
            'fuel' => 'nullable|numeric|min:0|max:9999999',
            'passengers' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string|max:3000',
        ]);

        $trip = DB::transaction(function () use ($request, $validated) {
            $vehicle = Access::scope(AdminRecord::where('module', 'vehicles'), $request->user())
                ->lockForUpdate()->findOrFail($validated['vehicle_id']);
            $driver = User::lockForUpdate()->findOrFail($request->user()->isSuperAdmin() ? $validated['driver_id'] : $request->user()->id);
            if ($vehicle->status !== 'Available') {
                throw ValidationException::withMessages(['vehicle_id' => 'This vehicle is not available.']);
            }
            if (! $driver->active || $driver->role !== 'driver') {
                throw ValidationException::withMessages(['driver_id' => 'Select an active OSO account.']);
            }
            if (! $request->user()->isSuperAdmin() && (trim($driver->location) === '' || strcasecmp(trim($driver->location), trim($vehicle->location)) !== 0)) {
                throw ValidationException::withMessages(['vehicle_id' => 'Choose a vehicle in your own office / location.']);
            }
            // An opening without a closing time reserves the vehicle and driver until completed.
            $conflict = AdminRecord::where('module', 'trips')->whereNotIn('status', ['Rejected', 'Archived'])
                ->where(fn ($q) => $q->where('data->vehicle_id', (string) $vehicle->id)->orWhere('data->driver_id', (string) $driver->id))
                ->where(function ($q) use ($validated) {
                    $q->whereNotIn('status', ['Completed', 'Closed'])
                        ->orWhere('data->return_at', '>', $validated['departure']);
                })->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['vehicle_id' => 'You or this vehicle have an unclosed or conflicting trip. Complete that trip before opening another.']);
            }
            if ((float) $validated['opening'] < (float) ($vehicle->data['odometer'] ?? 0)) {
                throw ValidationException::withMessages(['opening' => 'Mileage Before cannot be below the vehicle’s current mileage.']);
            }
            $data = [...$validated, 'vehicle_id' => (string) $vehicle->id, 'driver_id' => (string) $driver->id, 'opened_by' => (string) $request->user()->id];
            $trip = AdminRecord::create([
                'module' => 'trips', 'reference' => 'TRI-'.strtoupper(bin2hex(random_bytes(4))),
                'name' => $validated['name'], 'category' => $validated['category'],
                'department' => $driver->department, 'location' => $validated['from'],
                'status' => 'Pending', 'data' => $data,
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ]);
            PortalController::audit('Opened trip', $trip->reference);

            return $trip;
        });

        return redirect('/records/trips/'.$trip->id)->with('success', 'Opening details saved. The trip is Pending until closing details are completed.');
    }

    private function tripForClosing(Request $request, int $id, bool $lock = false): AdminRecord
    {
        $query = AdminRecord::where('module', 'trips');
        if ($lock) {
            $query->lockForUpdate();
        }
        $trip = $query->findOrFail($id);
        abort_unless($request->user()->active && ($request->user()->role === 'super_admin'
            || ($request->user()->role === 'driver'
                && (string) ($trip->data['driver_id'] ?? '') === (string) $request->user()->id)), 403);
        abort_unless($trip->status === 'Pending', 409, 'This trip is no longer pending. Refresh the record.');

        return $trip;
    }

    public function closeForm(Request $request, int $id)
    {
        $trip = $this->tripForClosing($request, $id);
        $driver = User::find($trip->data['driver_id']);
        $vehicle = AdminRecord::withTrashed()->find($trip->data['vehicle_id']);

        return view('trips.close', compact('trip', 'driver', 'vehicle'));
    }

    public function close(Request $request, int $id)
    {
        $this->tripForClosing($request, $id);
        $validated = $request->validate([
            'closing' => 'required|numeric|min:0|max:999999999.9',
            'return_at' => 'required|date_format:Y-m-d\TH:i',
            'version' => 'required|integer|min:1',
        ]);
        DB::transaction(function () use ($request, $id, $validated) {
            $trip = $this->tripForClosing($request, $id, true);
            abort_unless($trip->version === (int) $validated['version'], 409, 'This trip changed. Refresh before closing it.');
            if ((float) $validated['closing'] < (float) $trip->data['opening']) {
                throw ValidationException::withMessages(['closing' => 'Mileage After must be greater than or equal to Mileage Before.']);
            }
            if ($validated['return_at'] < $trip->data['departure']) {
                throw ValidationException::withMessages(['return_at' => 'Return date and time cannot be before departure.']);
            }
            $vehicle = AdminRecord::where('module', 'vehicles')->lockForUpdate()->findOrFail($trip->data['vehicle_id']);
            $trip->data = [...$trip->data, 'closing' => $validated['closing'], 'return_at' => $validated['return_at'],
                'distance' => round((float) $validated['closing'] - (float) $trip->data['opening'], 1),
                'closed_by' => (string) $request->user()->id, 'closed_at' => now()->toIso8601String()];
            $trip->status = 'Completed';
            $trip->version++;
            $trip->updated_by = $request->user()->id;
            $trip->save();
            $vehicle->update(['data' => [...$vehicle->data, 'odometer' => max((float) ($vehicle->data['odometer'] ?? 0), (float) $validated['closing'])],
                'version' => $vehicle->version + 1, 'updated_by' => $request->user()->id]);
            PortalController::audit('Completed trip', $trip->reference);
        });

        return redirect('/records/trips/'.$id)->with('success', 'Closing details saved. The trip is Completed.');
    }

    public function addNote(Request $request, int $id)
    {
        $trip = Access::scope(AdminRecord::where('module', 'trips'), $request->user())->findOrFail($id);
        abort_unless(Access::canAddTripNote($request->user(), $trip), 403);
        $validated = $request->validate(['note' => 'required|string|max:3000']);
        DB::transaction(function () use ($trip, $request, $validated) {
            DB::table('trip_notes')->insert(['record_id' => $trip->id, 'user_id' => $request->user()->id, 'note' => $validated['note'], 'created_at' => now(), 'updated_at' => now()]);
            PortalController::audit('Added trip note', $trip->reference);
        });

        return back()->with('success', 'Additional note added.');
    }
}

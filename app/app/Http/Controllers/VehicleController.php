<?php

namespace App\Http\Controllers;

use App\Models\AdminRecord;
use App\Models\User;
use App\Services\Access;
use App\Services\VehicleFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleController extends Controller
{
    public function deleteForm(Request $request, int $id)
    {
        abort_unless($request->user()->active && $request->user()->isSuperAdmin(), 403);
        $record = AdminRecord::where('module', 'vehicles')->findOrFail($id);
        $words = ['ACACIA', 'RIVER', 'ORANGE', 'HORIZON', 'FOREST', 'ANCHOR'];
        $word = $words[random_int(0, count($words) - 1)];
        $request->session()->put('vehicle_delete.'.$id, ['word' => $word, 'user' => $request->user()->id, 'version' => $record->version, 'expires' => time() + 600]);

        return view('vehicles.delete', compact('record', 'word'));
    }

    public function delete(Request $request, int $id)
    {
        abort_unless($request->user()->active && $request->user()->isSuperAdmin(), 403);
        $v = $request->validate(['current_password' => 'required|current_password', 'confirmation_word' => 'required|string', 'version' => 'required|integer']);
        $challenge = $request->session()->get('vehicle_delete.'.$id);
        if (! $challenge || $challenge['user'] !== $request->user()->id || $challenge['expires'] < time() || ! hash_equals($challenge['word'], $v['confirmation_word'])) {
            throw ValidationException::withMessages(['confirmation_word' => 'Enter the displayed confirmation word. If it expired, reopen the delete page.']);
        }
        DB::transaction(function () use ($request, $id, $v, $challenge) {
            $record = AdminRecord::where('module', 'vehicles')->lockForUpdate()->findOrFail($id);
            abort_unless($record->version === (int) $v['version'] && $record->version === $challenge['version'], 409, 'Vehicle changed. Reopen the delete page.');
            if (AdminRecord::where('module', 'trips')->where(fn ($q) => $q->where('data->vehicle_id', (string) $id)->orWhere('data->vehicle_id', $id))->where('status', '!=', 'Completed')->exists()) {
                throw ValidationException::withMessages(['vehicle' => 'Close this vehicle’s unfinished trips before deleting it.']);
            }
            $record->updated_by = $request->user()->id;
            $record->version++;
            $record->save();
            PortalController::audit('Deleted vehicle', $record->reference);
            $record->delete();
        });
        $request->session()->forget('vehicle_delete.'.$id);

        return redirect('/records/vehicles')->with('success', 'Vehicle deleted from the active fleet. Linked trip history has been retained.');
    }

    public function form(Request $request, ?int $id = null)
    {
        abort_unless(Access::allows($request->user(), 'vehicles.write'), 403);
        $record = $id ? AdminRecord::where('module', 'vehicles')->findOrFail($id) : null;
        $drivers = User::where('active', true)->where('role', 'driver')->orderBy('name')->get();

        return view('vehicles.form', compact('record', 'drivers'));
    }

    public function save(Request $request, ?int $id = null)
    {
        abort_unless(Access::allows($request->user(), 'vehicles.write'), 403);
        $validated = $request->validate([...VehicleFields::rules(), ...($id ? ['version' => 'required|integer|min:1'] : [])]);
        $validated['registration'] = strtoupper(trim($validated['registration']));
        if (! empty($validated['driver_id'])) {
            $driver = User::findOrFail($validated['driver_id']);
            if (! $driver->active || $driver->role !== 'driver') {
                throw ValidationException::withMessages(['driver_id' => 'Select an active OSO account.']);
            }
            $validated['driver_id'] = (string) $driver->id;
        }
        $record = DB::transaction(function () use ($request, $validated, $id) {
            $record = $id ? AdminRecord::where('module', 'vehicles')->lockForUpdate()->findOrFail($id) : new AdminRecord;
            if ($id) {
                abort_unless($record->version === (int) $validated['version'], 409, 'This vehicle changed. Refresh before saving.');
            }
            if (AdminRecord::withTrashed()->where('module', 'vehicles')->where('unique_key', 'vehicle:'.$validated['registration'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                throw ValidationException::withMessages(['registration' => 'This registration number already exists, including in retained vehicle history.']);
            }
            $data = $validated;
            unset($data['version']);
            $record->forceFill(['module' => 'vehicles', 'name' => $validated['name'], 'location' => $validated['location'], 'unique_key' => 'vehicle:'.$validated['registration'], 'data' => array_merge($record->data ?? [], $data), 'updated_by' => $request->user()->id]);
            if (! $id) {
                $record->forceFill(['reference' => 'VEH-'.strtoupper(bin2hex(random_bytes(4))), 'category' => '', 'department' => '', 'status' => 'Available', 'created_by' => $request->user()->id]);
            } else {
                $record->version++;
            }
            $record->save();
            PortalController::audit($id ? 'Updated vehicle' : 'Created vehicle', $record->reference);

            return $record;
        });

        return redirect('/records/vehicles/'.$record->id)->with('success', $id ? 'Vehicle updated.' : 'Vehicle added.');
    }
}

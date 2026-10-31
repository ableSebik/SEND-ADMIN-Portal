<?php

namespace App\Http\Controllers;

use App\Models\AdminRecord;
use App\Services\Access;
use App\Services\FleetOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FleetOperationsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Access::allows($request->user(), 'view') && $request->user()->role !== 'driver', 403);
        $vehicles = AdminRecord::where('module', 'vehicles')->orderBy('name')->get();
        $selected = $request->validate(['vehicle' => 'nullable|integer'])['vehicle'] ?? null;
        $operations = DB::table('vehicle_operations as o')->join('records as v', 'v.id', '=', 'o.vehicle_id')
            ->whereNull('v.deleted_at')->when($selected, fn ($q) => $q->where('v.id', $selected))
            ->select('o.*', 'v.name as vehicle_name', 'v.reference as vehicle_reference')->orderByDesc('o.id')->paginate(25)->withQueryString();
        $events = DB::table('vehicle_events as e')->join('records as v', 'v.id', '=', 'e.vehicle_id')
            ->join('users as u', 'u.id', '=', 'e.created_by')->when($selected, fn ($q) => $q->where('v.id', $selected))
            ->select('e.*', 'v.name as vehicle_name', 'u.name as actor')->orderByDesc('e.id')->limit(50)->get();
        $deliveries = DB::table('renewal_deliveries as d')->join('vehicle_operations as o', 'o.id', '=', 'd.operation_id')
            ->join('users as u', 'u.id', '=', 'd.user_id')->join('records as v', 'v.id', '=', 'o.vehicle_id')
            ->when($selected, fn ($q) => $q->where('v.id', $selected))->select('d.*', 'u.name as recipient', 'v.name as vehicle_name', 'o.kind')->orderByDesc('d.id')->limit(30)->get();

        $serviceRequests = DB::table('vehicle_service_requests as s')->join('records as v', 'v.id', '=', 's.vehicle_id')
            ->join('users as u', 'u.id', '=', 's.requested_by')->when($selected, fn ($q) => $q->where('v.id', $selected))
            ->select('s.*', 'v.name as vehicle_name', 'u.name as requester')->orderByDesc('s.id')->limit(50)->get();

        return view('vehicles.operations', compact('vehicles', 'operations', 'events', 'deliveries', 'selected', 'serviceRequests'));
    }

    public function store(Request $request)
    {
        abort_unless(Access::allows($request->user(), 'vehicles.write'), 403);
        $v = $request->validate([
            'vehicle_id' => 'required|integer', 'version' => 'required|integer',
            'kind' => ['required', Rule::in(array_keys(FleetOperations::KINDS))],
            'completed_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'due_on' => 'required|date_format:Y-m-d|after:completed_on',
            'provider' => 'nullable|string|max:180', 'reference' => 'nullable|string|max:180',
            'cost' => 'nullable|numeric|min:0|max:999999999999', 'notes' => 'nullable|string|max:3000',
            'completion_notes' => 'nullable|string|max:3000',
            'release_vehicle' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($v, $request) {
            $vehicle = AdminRecord::where('module', 'vehicles')->lockForUpdate()->findOrFail($v['vehicle_id']);
            abort_unless($vehicle->version === (int) $v['version'], 409, 'Vehicle changed. Refresh before saving.');
            $old = DB::table('vehicle_operations')->where('vehicle_id', $vehicle->id)->where('kind', $v['kind'])->whereNull('superseded_at')->pluck('id');
            DB::table('vehicle_operations')->whereIn('id', $old)->update(['superseded_at' => now(), 'updated_at' => now()]);
            DB::table('renewal_deliveries')->whereIn('operation_id', $old)->where('status', '!=', 'Sent')->update(['status' => 'Cancelled', 'updated_at' => now()]);
            $entry = $v;
            unset($entry['version'], $entry['release_vehicle']);
            $operationId = DB::table('vehicle_operations')->insertGetId([...$entry, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            if ($v['kind'] === 'service') {
                DB::table('vehicle_service_requests')->where('vehicle_id', $vehicle->id)->whereNull('completed_at')
                    ->update(['operation_id' => $operationId, 'completed_at' => now(), 'updated_at' => now()]);
            }
            $key = ['service' => 'next_service', 'roadworthiness' => 'roadworthy_expiry', 'insurance' => 'insurance_expiry'][$v['kind']];
            $vehicle->update(['data' => [...$vehicle->data, $key => $v['due_on']], 'version' => $vehicle->version + 1, 'updated_by' => $request->user()->id]);
            if ($v['kind'] === 'service' && ($v['release_vehicle'] ?? false) && $vehicle->status === 'Maintenance') {
                FleetOperations::setStatus($vehicle, 'Available', 'Servicing completed and vehicle released.', $request->user()->id);
            }
            FleetOperations::event($vehicle, FleetOperations::KINDS[$v['kind']], 'Completed '.$v['completed_on'].'; next due '.$v['due_on'].'. Previous reminders cancelled.', $request->user()->id);
            PortalController::audit('Recorded vehicle renewal', $vehicle->reference);
        });

        return back()->with('success', 'Renewal saved. Reminders for the previous due date have stopped.');
    }

    public function status(Request $request, int $id)
    {
        abort_unless(Access::allows($request->user(), 'vehicles.write'), 403);
        $v = $request->validate(['status' => ['required', Rule::in(FleetOperations::STATUSES)], 'reason' => 'required|string|max:1000', 'version' => 'required|integer']);
        DB::transaction(function () use ($v, $request, $id) {
            $vehicle = AdminRecord::where('module', 'vehicles')->lockForUpdate()->findOrFail($id);
            abort_unless($vehicle->version === (int) $v['version'], 409, 'Vehicle changed. Refresh before updating its status.');
            if ($v['status'] === 'Available' && DB::table('vehicle_service_requests')->where('vehicle_id', $id)->whereNull('completed_at')->exists()) {
                throw ValidationException::withMessages(['status' => 'Record completed servicing before releasing this vehicle to Available.']);
            }
            FleetOperations::setStatus($vehicle, $v['status'], $v['reason'], $request->user()->id);
            PortalController::audit('Changed vehicle status', $vehicle->reference);
        });

        return back()->with('success', 'Vehicle status updated.');
    }

    public function serviceForm(Request $request, int $id)
    {
        abort_unless(Access::allows($request->user(), 'vehicles.write'), 403);
        $vehicle = AdminRecord::where('module', 'vehicles')->findOrFail($id);

        return view('vehicles.service', compact('vehicle'));
    }

    public function startService(Request $request, int $id)
    {
        abort_unless(Access::allows($request->user(), 'vehicles.write'), 403);
        $v = $request->validate(['version' => 'required|integer',
            'project' => ['required', 'string', Rule::in(array_merge(\App\Models\User::DEPARTMENTS, DB::table('asset_projects')->where('status', 'Active')->pluck('name')->all()))],
            'requested_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'service_type' => 'required|in:Routine,Repair,Accident,Other', 'reason' => 'required|string|max:3000',
            'provider' => 'nullable|string|max:180', 'procurement_method' => 'required|in:RFQ,Contract/LTA,Other',
            'diagnostic_report' => 'required|in:Yes,No,N/A']);
        DB::transaction(function () use ($id, $request, $v) {
            $vehicle = AdminRecord::where('module', 'vehicles')->lockForUpdate()->findOrFail($id);
            abort_unless($vehicle->version === (int) $v['version'], 409, 'Vehicle changed. Refresh before sending for servicing.');
            if (DB::table('vehicle_service_requests')->where('vehicle_id', $id)->whereNull('completed_at')->exists()) {
                throw ValidationException::withMessages(['vehicle' => 'This vehicle already has an open servicing request.']);
            }
            if (AdminRecord::where('module', 'trips')->where('data->vehicle_id', (string) $id)->whereNotIn('status', ['Completed', 'Closed', 'Rejected', 'Archived'])->exists()) {
                throw ValidationException::withMessages(['vehicle' => 'Close the vehicle’s pending trip before sending it for servicing.']);
            }
            $data = $v;
            unset($data['version']);
            DB::table('vehicle_service_requests')->insert([...$data, 'vehicle_id' => $id, 'mileage' => $vehicle->data['odometer'] ?? 0,
                'requested_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            FleetOperations::setStatus($vehicle, 'Maintenance', 'Sent for '.$v['service_type'].' servicing: '.$v['reason'], $request->user()->id);
            FleetOperations::event($vehicle, 'Servicing request', $v['service_type'].': '.$v['reason'], $request->user()->id);
            PortalController::audit('Sent vehicle for servicing', $vehicle->reference);
        });

        return redirect('/operational-history?vehicle='.$id)->with('success', 'Servicing request saved. Vehicle status changed to Maintenance.');
    }
}

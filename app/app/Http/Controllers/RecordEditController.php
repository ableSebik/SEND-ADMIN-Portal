<?php

namespace App\Http\Controllers;

use App\Models\AdminRecord;
use App\Services\Access;
use App\Services\AssetFields;
use App\Services\RecordFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecordEditController extends Controller
{
    public function form(string $module, int $id)
    {
        if ($module === 'assets') {
            return app(AssetRegisterController::class)->form(request(), $id);
        }
        if ($module === 'vehicles') {
            return app(VehicleController::class)->form(request(), $id);
        }
        abort_unless(Access::allows(Auth::user(), 'create') && in_array($module, ['vendors', 'vehicles', 'assets']), 403);
        $record = Access::scope(AdminRecord::where('module', $module), Auth::user())->findOrFail($id);

        return view('edit', compact('record', 'module'));
    }

    public function submit(Request $r, string $module, int $id)
    {
        if ($module === 'vehicles') {
            return app(VehicleController::class)->save($r, $id);
        }
        abort_unless(Access::allows(Auth::user(), 'create') && in_array($module, ['vendors', 'vehicles', 'assets']), 403);
        $v = $module === 'assets'
            ? AssetFields::prepare($r->validate([...AssetFields::rules(), 'version' => 'required|integer']))
            : $r->validate(['name' => 'required|string|max:180', 'category' => 'required|string|max:80', 'department' => 'required|string|max:100', 'location' => 'required|string|max:100', 'notes' => 'nullable|string|max:3000', 'version' => 'required|integer', ...RecordFields::rules($module)]);
        DB::transaction(function () use ($id, $module, $v) {
            $record = Access::scope(AdminRecord::where('module', $module), Auth::user())->lockForUpdate()->findOrFail($id);
            abort_unless($record->version === (int) $v['version'] && $record->status !== 'Disposed', 409);
            abort_if(DB::table('approvals')->where('record_id', $id)->where('status', 'Pending')->exists(), 409, 'An approval is already pending.');
            $data = $v;
            unset($data['version'],$data['odometer']);
            $previous = ['name' => $record->name, 'category' => $record->category, 'department' => $record->department, 'location' => $record->location, 'data' => $record->data];
            DB::table('approvals')->insert(['record_id' => $id, 'action' => 'record_update', 'payload' => json_encode(['proposed' => $data, 'previous' => $previous]), 'status' => 'Pending', 'requested_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
            $record->increment('version');
            PortalController::audit('Requested record update', $record->reference);
        });

        return redirect('/records/'.$module.'/'.$id)->with('success', 'Changes submitted for independent approval.');
    }
}

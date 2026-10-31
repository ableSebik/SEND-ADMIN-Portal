<?php

namespace App\Http\Controllers;

use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferenceDataController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Access::allows($request->user(), 'vehicles.write'), 403);

        return view('reference-data', ['donors' => DB::table('asset_donors')->orderBy('name')->get(),
            'projects' => DB::table('asset_projects as p')->leftJoin('asset_donors as d', 'd.id', '=', 'p.donor_id')
                ->select('p.*', 'd.name as donor')->orderBy('p.name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless(Access::allows($request->user(), 'vehicles.write'), 403);
        $v = $request->validate(['kind' => 'required|in:donor,project', 'name' => 'required|string|max:180',
            'code' => ['required_if:kind,project', 'nullable', 'string', 'max:80'],
            'donor_id' => 'nullable|integer|exists:asset_donors,id',
            'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date']);
        $name = preg_replace('/\s+/u', ' ', trim($v['name']));
        $normalized = mb_strtolower($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Enter a name.']);
        }
        DB::transaction(function () use ($v, $name, $normalized) {
            if ($v['kind'] === 'donor') {
                if (DB::table('asset_donors')->where('normalized_name', $normalized)->exists()) {
                    throw ValidationException::withMessages(['name' => 'This donor already exists.']);
                }
                DB::table('asset_donors')->insert(['name' => $name, 'normalized_name' => $normalized, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $code = strtoupper(trim($v['code']));
                if ($code === '' || DB::table('asset_projects')->whereRaw('LOWER(name) = ?', [$normalized])->orWhere('code', $code)->exists()) {
                    throw ValidationException::withMessages(['name' => 'Use a unique project name and code.']);
                }
                DB::table('asset_projects')->insert(['name' => $name, 'code' => $code, 'donor_id' => $v['donor_id'] ?? null,
                    'start_date' => $v['start_date'] ?? null, 'end_date' => $v['end_date'] ?? null, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
            }
            PortalController::audit('Added '.$v['kind'], $name);
        });

        return back()->with('success', ucfirst($v['kind']).' added to the shared list.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AdminRecord;
use App\Models\User;
use App\Services\Access;
use App\Services\AssetFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetRegisterController extends Controller
{
    private function query(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:180', 'type' => ['nullable', Rule::in(array_keys(AssetFields::TYPES))], 'donor' => 'nullable|string|max:180', 'project' => 'nullable|string|max:180', 'location' => ['nullable', Rule::in(User::OFFICES)], 'status' => 'nullable|in:Available,Assigned,Awaiting acknowledgement,Disposed']);
        $q = Access::scope(AdminRecord::where('records.module', 'assets'), $r->user())->leftJoin('asset_details as detail', 'detail.record_id', '=', 'records.id')->select('records.*');
        foreach (['type' => 'asset_type', 'donor' => 'donor', 'project' => 'project'] as $filter => $column) {
            if ($r->filled($filter)) {
                $q->where('detail.'.$column, $r->input($filter));
            }
        }
        foreach (['location', 'status'] as $column) {
            if ($r->filled($column)) {
                $q->where('records.'.$column, $r->input($column));
            }
        }
        if ($r->filled('q')) {
            $q->where(function ($query) use ($r) {
                foreach (['records.name', 'records.reference', 'detail.office_id', 'detail.serial', 'detail.description', 'detail.staff_responsible'] as $column) {
                    $query->orWhere($column, 'like', '%'.$r->input('q').'%');
                }
            });
        }

        return $q;
    }

    public function index(Request $r)
    {
        abort_unless(Access::allows($r->user(), 'view'), 403);
        $records = $this->query($r)->orderByDesc('records.id')->paginate(15)->withQueryString();
        $visible = Access::scope(AdminRecord::where('module', 'assets'), $r->user())->select('id');
        $options = [];
        foreach (['donor', 'project'] as $column) {
            $options[$column] = DB::table('asset_details')->whereIn('record_id', $visible)->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column);
        }

        return view('assets.index', compact('records', 'options'));
    }

    public function form(Request $r, ?int $id = null)
    {
        abort_unless(Access::allows($r->user(), 'create'), 403);
        $record = $id ? Access::scope(AdminRecord::where('module', 'assets'), $r->user())->findOrFail($id) : null;
        if ($record) {
            abort_if($record->status === 'Disposed', 409, 'Disposed assets cannot be edited.');
        }
        $r->validate(['type' => ['nullable', Rule::in(array_keys(AssetFields::TYPES))]]);
        if ($r->boolean('choose_type')) {
            return view('assets.choose-type', compact('record'));
        }
        $type = old('asset_type', $r->input('type', AssetFields::type($record?->data['asset_type'] ?? $record?->category)));
        if (! $type || ! isset(AssetFields::TYPES[$type])) {
            return view('assets.choose-type', compact('record'));
        }

        return view('assets.form', compact('record', 'type'));
    }

    public function store(Request $r)
    {
        abort_unless(Access::allows($r->user(), 'create'), 403);
        $v = AssetFields::prepare($r->validate(AssetFields::rules()));
        $record = DB::transaction(function () use ($r, $v) {
            $record = AdminRecord::create(['module' => 'assets', 'reference' => 'AST-'.strtoupper(bin2hex(random_bytes(4))), 'name' => $v['name'], 'category' => $v['category'], 'department' => $v['department'], 'location' => $v['location'], 'status' => 'Available', 'data' => $v, 'created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);
            PortalController::audit('Created asset', $record->reference);

            return $record;
        });

        return redirect('/records/assets/'.$record->id)->with('success', 'Asset added.');
    }

    public function export(Request $r)
    {
        abort_unless(Access::allows($r->user(), 'export'), 403);
        $q = $this->query($r);
        PortalController::audit('Exported', 'assets');

        return response()->streamDownload(function () use ($q) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Year', 'Name of asset', 'Type', 'Description', 'Office ID / registration', 'Date of purchase', 'Supplier', 'Origin', 'Model', 'Make', 'Year of manufacture', 'Serial / chassis', 'Office / location', 'Staff responsible', 'Invoice no', 'Price GHS', 'Project', 'Donor', 'Department', 'Status']);
            foreach ($q->orderBy('records.id')->cursor() as $a) {
                $d = $a->data;
                $row = [$d['year'] ?? '', $a->name, $a->category, $d['description'] ?? '', $d['office_id'] ?? '', $d['purchase_date'] ?? '', $d['supplier'] ?? '', $d['origin'] ?? '', $d['model'] ?? '', $d['manufacturer'] ?? '', $d['manufacture_year'] ?? '', $d['serial'] ?? '', $a->location, $d['staff_responsible'] ?? '', $d['invoice'] ?? '', $d['price'] ?? $d['cost'] ?? '', $d['project'] ?? '', $d['donor'] ?? '', $a->department, $a->status];
                fputcsv($f, array_map(fn ($value) => preg_match('/^[=+@\-\t\r\n]/', (string) $value) || preg_match('/^[=+@\-]/', ltrim((string) $value)) ? "'".$value : $value, $row));
            }fclose($f);
        }, 'assets-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}

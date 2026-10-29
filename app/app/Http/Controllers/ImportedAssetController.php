<?php

namespace App\Http\Controllers;

use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportedAssetController extends Controller
{
    private function authorizeRead(Request $r): void
    {
        abort_unless(Access::allows($r->user(), 'view') && $r->user()->role !== 'driver', 403);
    }

    private function query(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:180', 'category_id' => 'nullable|integer', 'project_id' => 'nullable|integer', 'donor_id' => 'nullable|integer', 'location_id' => 'nullable|integer', 'custodian_id' => 'nullable|integer', 'supplier_id' => 'nullable|integer', 'year_from' => 'nullable|integer|min:1900|max:2100', 'year_to' => 'nullable|integer|min:1900|max:2100', 'cost_min' => 'nullable|numeric|min:0', 'cost_max' => 'nullable|numeric|min:0', 'status' => 'nullable|in:IN_USE,IN_STORE,UNDER_REPAIR,DISPOSED,STOLEN,LOST,TRANSFERRED_OUT', 'condition' => 'nullable|in:NEW,GOOD,FAIR,POOR', 'sort' => 'nullable|in:name,unit_cost,purchase_year,asset_tag', 'direction' => 'nullable|in:asc,desc', 'held_by_partner' => 'nullable|in:1', 'missing_tag' => 'nullable|in:1', 'missing_serial' => 'nullable|in:1', 'zero_cost' => 'nullable|in:1', 'has_exceptions' => 'nullable|in:1']);
        $q = DB::table('assets as a')->leftJoin('asset_categories as c', 'c.id', '=', 'a.category_id')->leftJoin('asset_projects as p', 'p.id', '=', 'a.project_id')->leftJoin('asset_donors as d', 'd.id', '=', 'a.donor_id')->leftJoin('asset_locations as l', 'l.id', '=', 'a.location_id')->leftJoin('asset_custodians as u', 'u.id', '=', 'a.custodian_id')->leftJoin('asset_suppliers as s', 's.id', '=', 'a.supplier_id')->select('a.*', 'c.name as category', 'p.name as project', 'd.name as donor', 'l.name as location', 'u.name as custodian', 's.name as supplier')->whereNull('a.deleted_at');
        if (! empty($v['q'])) {
            $q->where(function ($query) use ($v) {
                foreach (['a.name', 'a.description', 'a.serial_or_chassis_no', 'a.asset_tag', 'a.invoice_no', 'u.name'] as $field) {
                    $query->orWhere($field, 'like', '%'.$v['q'].'%');
                }
            });
        }
        foreach (['project_id', 'donor_id', 'location_id', 'custodian_id', 'supplier_id', 'status', 'condition'] as $field) {
            if (! empty($v[$field])) {
                $q->where('a.'.$field, $v[$field]);
            }
        }
        if (! empty($v['category_id'])) {
            $q->where(fn ($query) => $query->where('a.category_id', $v['category_id'])->orWhere('c.parent_id', $v['category_id']));
        }
        foreach (['year_from' => ['purchase_year', '>='], 'year_to' => ['purchase_year', '<='], 'cost_min' => ['unit_cost', '>='], 'cost_max' => ['unit_cost', '<=']] as $filter => [$field,$op]) {
            if (isset($v[$filter])) {
                $q->where('a.'.$field, $op, $v[$filter]);
            }
        }
        foreach (['missing_tag' => 'asset_tag', 'missing_serial' => 'serial_or_chassis_no'] as $filter => $field) {
            if (! empty($v[$filter])) {
                $q->whereNull('a.'.$field);
            }
        }
        if (! empty($v['zero_cost'])) {
            $q->where('a.unit_cost', 0);
        }if (! empty($v['held_by_partner'])) {
            $q->where('a.held_by_partner', true);
        }
        if (! empty($v['has_exceptions'])) {
            $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('asset_import_exceptions as e')->whereColumn('e.asset_id', 'a.id')->where('e.resolution', 'OPEN'));
        }

        return $q->orderBy('a.'.($v['sort'] ?? 'name'), $v['direction'] ?? 'asc')->orderBy('a.id');
    }

    public function index(Request $r)
    {
        $this->authorizeRead($r);
        $q = $this->query($r);
        $totals = (clone $q)->reorder()->select([])->selectRaw('COUNT(*) as count, SUM(a.unit_cost) as cost')->first();
        $assets = $q->paginate(25)->withQueryString();
        $lookups = [];
        foreach (['categories', 'projects', 'donors', 'locations', 'custodians', 'suppliers'] as $kind) {
            $lookups[$kind] = DB::table('asset_'.$kind)->orderBy('name')->get();
        }

        return view('assets.register', compact('assets', 'lookups', 'totals'));
    }

    public function show(Request $r, int $id)
    {
        $this->authorizeRead($r);
        $asset = $this->query(new Request)->where('a.id', $id)->first();
        abort_unless($asset, 404);
        $sources = DB::table('asset_sources')->where('asset_id', $id)->orderBy('import_batch_id')->get();
        $exceptions = DB::table('asset_import_exceptions')->where('asset_id', $id)->where('resolution', 'OPEN')->latest('id')->get();

        return view('assets.imported-show', compact('asset', 'sources', 'exceptions'));
    }

    public function export(Request $r)
    {
        $this->authorizeRead($r);
        abort_unless(Access::allows($r->user(), 'export'), 403);
        $q = $this->query($r);
        PortalController::audit('Exported', 'assets');

        return response()->streamDownload(function () use ($q) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Asset tag', 'Name', 'Category', 'Project', 'Donor', 'Year', 'Location', 'Custodian', 'Supplier', 'Status', 'Unit cost GHS', 'Cost basis', 'Held by partner', 'Serial']);
            foreach ($q->cursor() as $a) {
                $row = [$a->asset_tag, $a->name, $a->category, $a->project, $a->donor, $a->purchase_year, $a->location, $a->custodian, $a->supplier, $a->status, $a->unit_cost, $a->cost_basis, $a->held_by_partner ? 'Yes' : 'No', $a->serial_or_chassis_no];
                fputcsv($f, array_map(fn ($value) => preg_match('/^[=+@\-\t\r\n]/', (string) $value) || preg_match('/^[=+@\-]/', ltrim((string) $value)) ? "'".$value : $value, $row));
            }fclose($f);
        }, 'imported-assets.csv', ['Content-Type' => 'text/csv']);
    }
}

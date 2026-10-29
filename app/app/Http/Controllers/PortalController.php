<?php

namespace App\Http\Controllers;

use App\Models\AdminRecord;
use App\Models\User;
use App\Models\VendorBankAccount;
use App\Services\Access;
use App\Services\AuditCategory;
use App\Services\RecordFields;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    public const MODULES = ['vendors' => 'Vendor Management', 'vehicles' => 'Vehicles', 'trips' => 'Trips', 'assets' => 'Asset Register'];

    public static function audit(string $action, string $subject): void
    {
        DB::table('audit_events')->insert(['user_id' => Auth::id(), 'action' => $action, 'subject' => $subject, 'category' => AuditCategory::resolve($action, $subject), 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = strtolower($v['email']).'|'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }if (! Auth::attempt([...$v, 'active' => true])) {
            RateLimiter::hit($key, 300);
            self::audit('Login failed', 'Account authentication');
            throw ValidationException::withMessages(['email' => 'The credentials could not be verified.']);
        }RateLimiter::clear($key);
        $r->session()->regenerate();
        self::audit('Signed in', 'Account');

        if (Auth::user()->role === 'driver') {
            $r->session()->forget('url.intended');

            return redirect('/records/trips');
        }

        return redirect()->intended('/');
    }

    public function logout(Request $r)
    {
        self::audit('Signed out', 'Account');
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }

    public function dashboard()
    {
        if (Auth::user()->role === 'driver') {
            return redirect('/records/trips');
        }
        $q = Access::scope(AdminRecord::query(), Auth::user());
        $counts = [];
        foreach (self::MODULES as $k => $v) {
            $counts[$k] = (clone $q)->where('module', $k)->count();
        }$pending = DB::table('approvals')->where('status', 'Pending')->when(! Access::allows(Auth::user(), 'approve'), fn ($q) => $q->where('requested_by', Auth::id()))->count();
        $activity = DB::table('audit_events')->when(! Access::allows(Auth::user(), 'audit'), fn ($q) => $q->where('user_id', Auth::id()))->latest('id')->limit(7)->get();

        return view('dashboard', compact('counts', 'pending', 'activity'));
    }

    public function index(Request $r, string $module)
    {
        if ($module === 'assets') {
            return app(AssetRegisterController::class)->index($r);
        }
        abort_unless(isset(self::MODULES[$module]) && Access::allows(Auth::user(), 'view'), 403);
        $q = Access::scope(AdminRecord::where('module', $module), Auth::user());
        if ($r->filled('q')) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->q.'%')->orWhere('reference', 'like', '%'.$r->q.'%'));
        }if ($r->filled('status')) {
            $q->where('status', $r->status);
        }$records = $q->latest()->paginate(15)->withQueryString();

        return view($module === 'trips' ? 'trips.index' : ($module === 'vehicles' ? 'vehicles.index' : 'records'), compact('module', 'records'));
    }

    public function form(string $module)
    {
        if ($module === 'assets') {
            return app(AssetRegisterController::class)->form(request());
        }
        if ($module === 'vehicles') {
            return app(VehicleController::class)->form(request());
        }
        if ($module === 'trips') {
            return app(TripController::class)->create(request());
        }
        abort_unless(isset(self::MODULES[$module]) && (Access::allows(Auth::user(), 'create') || ($module === 'trips' && Access::allows(Auth::user(), 'trip'))), 403);
        $vehicles = Access::scope(AdminRecord::where('module', 'vehicles'), Auth::user())->where('status', 'Available')->get();
        $people = User::where('active', true)->get();

        return view('form', compact('module', 'vehicles', 'people'));
    }

    public function store(Request $r, string $module)
    {
        if ($module === 'assets') {
            return app(AssetRegisterController::class)->store($r);
        }
        if ($module === 'vehicles') {
            return app(VehicleController::class)->save($r);
        }
        if ($module === 'trips') {
            return app(TripController::class)->store($r);
        }
        abort_unless(isset(self::MODULES[$module]) && (Access::allows(Auth::user(), 'create') || ($module === 'trips' && Access::allows(Auth::user(), 'trip'))), 403);
        $v = $r->validate(['name' => 'required|string|max:180', 'category' => 'required|string|max:80', 'department' => 'required|string|max:100', 'location' => 'required|string|max:100', 'notes' => 'nullable|string|max:3000', 'email' => 'nullable|email|max:180', 'phone' => 'nullable|string|max:30', 'registration' => ($module === 'vehicles' ? 'required' : 'nullable').'|string|max:40', 'cost' => 'nullable|numeric|min:0', 'serial' => 'nullable|string|max:100', 'licence_expiry' => 'nullable|date', 'vehicle_id' => ($module === 'trips' ? 'required' : 'nullable').'|integer', 'driver_id' => ($module === 'trips' ? 'required' : 'nullable').'|exists:users,id', 'departure' => ($module === 'trips' ? 'required' : 'nullable').'|date', 'return_at' => ($module === 'trips' ? 'required' : 'nullable').'|date|after:departure', 'opening' => ($module === 'trips' ? 'required' : 'nullable').'|numeric|min:0', 'closing' => 'nullable|numeric|gte:opening', 'fuel' => 'nullable|numeric|min:0']);
        $v = array_merge($v, $r->validate(RecordFields::rules($module)));
        $record = DB::transaction(function () use ($module, $v) {
            if ($module === 'vehicles') {
                $v['registration'] = strtoupper($v['registration']);
                if (AdminRecord::where('module', 'vehicles')->where('data->registration', $v['registration'])->exists()) {
                    throw ValidationException::withMessages(['registration' => 'This registration number already exists.']);
                }
            }
            $record = AdminRecord::create(['unique_key' => $module === 'vehicles' ? 'vehicle:'.$v['registration'] : null, 'module' => $module, 'reference' => strtoupper(substr($module, 0, 3)).'-'.strtoupper(bin2hex(random_bytes(4))), 'name' => $v['name'], 'category' => $v['category'], 'department' => $v['department'], 'location' => $v['location'], 'status' => $module === 'vendors' ? 'Pending review' : 'Available', 'data' => $v, 'created_by' => Auth::id(), 'updated_by' => Auth::id()]);
            if ($module === 'vendors') {
                $this->approval($record, 'activate', []);
            }self::audit('Created', $record->reference);

            return $record;
        });

        return redirect('/records/'.$module.'/'.$record->id)->with('success', 'Record saved successfully.');
    }

    private function approval(AdminRecord $record, string $action, array $payload): void
    {
        DB::table('approvals')->insert(['record_id' => $record->id, 'action' => $action, 'payload' => json_encode($payload), 'status' => 'Pending', 'requested_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function show(string $module, int $id)
    {
        $record = Access::scope(AdminRecord::where('module', $module), Auth::user())->findOrFail($id);
        $history = DB::table('approvals')->where('record_id', $id)->latest()->get();
        $people = User::where('active', true)->get();
        $documents = DB::table('documents')->where('record_id', $id)->when(Auth::user()->role === 'driver' && $module !== 'trips', fn ($q) => $q->whereIn('type', ['Handover', 'Trip authorization']))->latest()->get();
        $entries = DB::table('record_entries')->where('record_id', $id)->when(! in_array(Auth::user()->role, ['super_admin', 'admin']), fn ($q) => $q->where(fn ($q) => $q->where('type', '!=', 'Incident')->orWhere('created_by', Auth::id())))->latest()->get();

        $tripNotes = $module === 'trips' ? DB::table('trip_notes')->join('users', 'users.id', '=', 'trip_notes.user_id')->where('record_id', $id)->select('trip_notes.*', 'users.name')->orderByDesc('trip_notes.id')->get() : collect();

        return view($module === 'trips' ? 'trips.show' : ($module === 'vehicles' ? 'vehicles.show' : 'show'), compact('record', 'module', 'history', 'people', 'documents', 'entries', 'tripNotes'));
    }

    public function action(Request $r, string $module, int $id)
    {
        abort_if($module === 'trips', 403, 'Use the trip closing form. Only the OSO who opened the trip or a Super Administrator can close it.');
        abort_unless(Access::allows(Auth::user(), 'create'), 403);
        $v = $r->validate(['action' => 'required|in:assign,transfer,dispose,close', 'custodian_id' => 'nullable|exists:users,id', 'reason' => 'required|string|max:1000', 'closing' => 'nullable|numeric|min:0', 'version' => 'required|integer']);
        DB::transaction(function () use ($module, $id, $v) {
            $record = Access::scope(AdminRecord::where('module', $module), Auth::user())->lockForUpdate()->findOrFail($id);
            abort_unless($record->version == $v['version'], 409, 'This record changed. Reload before continuing.');
            if (DB::table('approvals')->where('record_id', $id)->where('status', 'Pending')->exists()) {
                throw ValidationException::withMessages(['action' => 'An approval is already pending.']);
            }
            if ($module === 'assets') {
                if (! in_array($v['action'], ['assign', 'transfer', 'dispose'])) {
                    abort(422);
                }if ($v['action'] === 'assign' && $record->status !== 'Available') {
                    abort(422, 'Asset is not available.');
                }if ($v['action'] === 'transfer' && $record->status !== 'Assigned') {
                    abort(422);
                }if (! in_array($record->status, ['Available', 'Assigned'])) {
                    abort(422);
                }if (in_array($v['action'], ['assign', 'transfer']) && empty($v['custodian_id'])) {
                    throw ValidationException::withMessages(['custodian_id' => 'A recipient is required.']);
                }
            } else {
                abort(422);
            }
            $this->approval($record, $v['action'], $v);
            $record->increment('version');
            self::audit('Requested '.$v['action'], $record->reference);
        });

        return back()->with('success', 'Request submitted for independent approval.');
    }

    public function approvals()
    {
        abort_unless(Access::allows(Auth::user(), 'approve'), 403);
        $approvals = DB::table('approvals')->join('records', 'records.id', '=', 'approvals.record_id')->join('users', 'users.id', '=', 'approvals.requested_by')->select('approvals.*', 'records.reference', 'records.name as record_name', 'users.name as requester')->orderByDesc('approvals.id')->paginate(20);

        return view('approvals', compact('approvals'));
    }

    public function decide(Request $r, int $id)
    {
        abort_unless(Access::allows(Auth::user(), 'approve'), 403);
        $v = $r->validate(['decision' => 'required|in:Approved,Rejected', 'reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($id, $v) {
            $a = DB::table('approvals')->where('id', $id)->lockForUpdate()->first();
            abort_unless($a, 404);
            abort_if($a->requested_by === Auth::id(), 403, 'You cannot approve your own request.');
            abort_unless($a->status === 'Pending', 409);
            $record = AdminRecord::lockForUpdate()->findOrFail($a->record_id);
            abort_if(in_array($record->module, ['trips', 'vehicles']), 409, 'Trip records use opening and closing details, not approval decisions.');
            $payload = json_decode($a->payload, true);
            $data = $record->data;
            if ($v['decision'] === 'Approved') {
                if ($a->action === 'record_update') {
                    foreach (['name', 'category', 'department', 'location'] as $field) {
                        $record->$field = $payload['proposed'][$field];
                    }
                    $data = array_merge($data, $payload['proposed']);
                }
                if ($a->action === 'bank_change') {
                    abort_unless(in_array(Auth::user()->role, ['super_admin', 'admin']), 403);
                    $bank = json_decode(Crypt::decryptString($payload['encrypted']), true);
                    VendorBankAccount::updateOrCreate(['record_id' => $record->id], ['details' => $bank]);
                }if ($a->action === 'activate') {
                    $record->status = $record->module === 'vendors' ? 'Active' : 'Approved';
                }if (in_array($a->action, ['assign', 'transfer'])) {
                    $data['custodian_id'] = (string) $payload['custodian_id'];
                    $data['acknowledged'] = false;
                    $record->status = 'Awaiting acknowledgement';
                }if ($a->action === 'dispose') {
                    $record->status = 'Disposed';
                    unset($data['custodian_id']);
                }
            } elseif ($a->action === 'activate') {
                $record->status = 'Rejected';
            }
            $record->data = $data;
            $record->version++;
            $record->updated_by = Auth::id();
            $record->save();
            DB::table('approvals')->where('id', $id)->update(['status' => $v['decision'], 'reason' => $v['reason'], 'decided_by' => Auth::id(), 'updated_at' => now()]);
            self::audit($v['decision'].' '.$a->action, $record->reference);
        });

        return back()->with('success', 'Decision recorded.');
    }

    public function acknowledge(int $id)
    {
        DB::transaction(function () use ($id) {
            $record = AdminRecord::lockForUpdate()->findOrFail($id);
            abort_unless(($record->data['custodian_id'] ?? null) == Auth::id() && $record->status === 'Awaiting acknowledgement', 403);
            $data = $record->data;
            $data['acknowledged'] = true;
            $record->update(['data' => $data, 'status' => 'Assigned', 'version' => $record->version + 1, 'updated_by' => Auth::id()]);
            self::audit('Acknowledged receipt', $record->reference);
        });

        return back()->with('success', 'Asset receipt acknowledged.');
    }

    public function auditPage(Request $request)
    {
        abort_unless(Access::allows(Auth::user(), 'audit'), 403);
        $filters = $request->validate(['category' => 'nullable|in:account,vehicle,trip,asset,user', 'from_date' => 'nullable|date_format:Y-m-d', 'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date']);
        $query = DB::table('audit_events')->leftJoin('users', 'users.id', '=', 'audit_events.user_id')->select('audit_events.*', 'users.name');
        if (! empty($filters['category'])) {
            $query->where('audit_events.category', $filters['category']);
            if ($filters['category'] === 'account') {
                $query->whereIn('action', ['Signed in', 'Signed out']);
            }
        }
        if (! empty($filters['from_date'])) {
            $query->where('audit_events.created_at', '>=', Carbon::parse($filters['from_date'], 'Africa/Accra')->startOfDay()->utc());
        }
        if (! empty($filters['to_date'])) {
            $query->where('audit_events.created_at', '<', Carbon::parse($filters['to_date'], 'Africa/Accra')->addDay()->startOfDay()->utc());
        }
        $events = $query->orderByDesc('audit_events.id')->paginate(30)->withQueryString();

        return view('audit', compact('events'));
    }

    public function users()
    {
        abort_unless(Access::allows(Auth::user(), 'users'), 403);
        $users = User::orderBy('name')->get();

        return view('users', compact('users'));
    }

    public function createUser(Request $r)
    {
        return app(UserManagementController::class)->save($r);
    }

    public function export(string $module)
    {
        if ($module === 'assets') {
            return app(AssetRegisterController::class)->export(request());
        }
        abort_unless(isset(self::MODULES[$module]) && Access::allows(Auth::user(), 'export'), 403);
        self::audit('Exported', $module);

        return response()->streamDownload(function () use ($module) {
            $f = fopen('php://output', 'w');
            fputcsv($f, $module === 'trips'
                ? ['Reference', 'Purpose of journey', 'OSO ID', 'Department/Project', 'From', 'Destination', 'Departure date and time', 'Mileage Before', 'Mileage After', 'Return date and time', 'Distance (km)', 'Status']
                : ($module === 'vehicles' ? ['Reference', 'Vehicle', 'Registration', 'Office / location', 'Mileage (km)', 'Status'] : ['Reference', 'Name', 'Category', 'Department', 'Location', 'Status']));
            Access::scope(AdminRecord::where('module', $module), Auth::user())->orderBy('id')->chunk(200, function ($rows) use ($f, $module) {
                foreach ($rows as $r) {
                    $row = $module === 'trips'
                        ? [$r->reference, $r->name, $r->data['driver_id'] ?? '', $r->category, $r->data['from'] ?? $r->location, $r->data['destination'] ?? '', $r->data['departure'] ?? '', $r->data['opening'] ?? '', $r->status === 'Completed' ? ($r->data['closing'] ?? '') : '', $r->status === 'Completed' ? ($r->data['return_at'] ?? '') : '', $r->status === 'Completed' ? ($r->data['distance'] ?? '') : '', $r->status]
                        : ($module === 'vehicles' ? [$r->reference, $r->name, $r->data['registration'] ?? '', $r->location, $r->data['odometer'] ?? 0, $r->status] : [$r->reference, $r->name, $r->category, $r->department, $r->location, $r->status]);
                    fputcsv($f, array_map(fn ($s) => preg_match('/^[=+@\-\t\r]/', (string) $s) ? "'".$s : $s, $row));
                }
            });
            fclose($f);
        }, $module.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}

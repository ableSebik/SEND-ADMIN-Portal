<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ImportedAssetController;
use App\Http\Controllers\PortalController as P;
use App\Http\Controllers\RecordEditController;
use App\Http\Controllers\RecordSupportController;
use App\Http\Controllers\SecurityController as S;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\FleetOperationsController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Middleware\AccountSecurity;
use App\Models\User;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/forgot-password', 'password-reset')->name('password.request');
    Route::post('/forgot-password', [S::class, 'sendReset']);
    Route::get('/reset-password/{token}', fn (string $token) => view('password-reset', compact('token')))->name('password.reset');
    Route::post('/reset-password', [S::class, 'reset']);
});
Route::middleware('auth')->group(function () {
    Route::get('/account', [S::class, 'page'])->middleware(AccountSecurity::class);
    Route::post('/account/password', [S::class, 'changePassword'])->middleware(AccountSecurity::class);
    Route::get('/security', [S::class, 'page']);
    Route::post('/security/password', [S::class, 'changePassword'])->middleware(AccountSecurity::class);
    Route::post('/logout', [P::class, 'logout']);
});
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => app()->environment('local') && ! User::exists() ? redirect('/setup') : view('login'))->name('login');
    Route::post('/login', [P::class, 'login']);
});
Route::middleware('guest')->group(function () {
    $checkSetup = function () {
        abort_unless(app()->environment('local') && ! User::exists(), 404);
    };
    Route::get('/setup', function () use ($checkSetup) {
        $checkSetup();

        return view('setup');
    });
    Route::post('/setup', function (Request $r) use ($checkSetup) {
        $checkSetup();
        $v = $r->validate(['name' => 'required|string|max:180', 'email' => 'required|email|unique:users|max:180', 'password' => 'required|string|min:8|confirmed']);
        $user = DB::transaction(function () use ($v) {
            abort_if(User::lockForUpdate()->exists(), 409);
            $user = new User;
            $user->forceFill([...$v, 'role' => 'super_admin'])->save();

            return $user;
        });
        auth()->login($user);
        $r->session()->regenerate();
        P::audit('Initial administrator created', 'Account');

        return redirect('/');
    })->middleware('throttle:5,1');
});
Route::middleware(['auth', AccountSecurity::class])->group(function () {
    Route::get('/assets/register', [ImportedAssetController::class, 'index']);
    Route::get('/operational-history', [FleetOperationsController::class, 'index']);
    Route::post('/operational-history', [FleetOperationsController::class, 'store']);
    Route::post('/vehicles/{id}/status', [FleetOperationsController::class, 'status']);
    Route::get('/vehicles/{id}/service', [FleetOperationsController::class, 'serviceForm']);
    Route::post('/vehicles/{id}/service', [FleetOperationsController::class, 'startService']);
    Route::get('/reference-data', [ReferenceDataController::class, 'index']);
    Route::post('/reference-data', [ReferenceDataController::class, 'store']);
    Route::get('/assets/register/export', [ImportedAssetController::class, 'export']);
    Route::get('/assets/register/{id}', [ImportedAssetController::class, 'show']);
    Route::get('/records/vehicles/{id}/delete', [VehicleController::class, 'deleteForm']);
    Route::post('/records/vehicles/{id}/delete', [VehicleController::class, 'delete'])->middleware('throttle:5,1');
    Route::post('/records/trips/{id}/notes', [TripController::class, 'addNote']);
    Route::get('/records/trips/{id}/close', [TripController::class, 'closeForm']);
    Route::post('/records/trips/{id}/close', [TripController::class, 'close']);
    Route::get('/', [P::class, 'dashboard'])->name('dashboard');
    Route::post('/vendors/{id}/bank', [AccountController::class, 'bank']);
    Route::post('/users/{id}/authorization', [AccountController::class, 'update']);
    Route::post('/records/{id}/documents', [RecordSupportController::class, 'upload']);
    Route::post('/records/{id}/entries', [RecordSupportController::class, 'entry']);
    Route::get('/documents/{id}', [RecordSupportController::class, 'download']);
    Route::get('/notifications', [RecordSupportController::class, 'notifications']);
    Route::get('/approvals', [P::class, 'approvals']);
    Route::post('/approvals/{id}', [P::class, 'decide']);
    Route::get('/audit', [P::class, 'auditPage']);
    Route::get('/users', [P::class, 'users']);
    Route::get('/users/new', [UserManagementController::class, 'form']);
    Route::get('/users/{id}/edit', [UserManagementController::class, 'form']);
    Route::post('/users/{id}', [UserManagementController::class, 'save']);
    Route::post('/users', [P::class, 'createUser']);
    Route::get('/reports', function () {
        abort_unless(Access::allows(auth()->user(), 'export'), 403);

        return view('reports');
    });
    Route::get('/records/{module}', [P::class, 'index']);
    Route::get('/records/{module}/{id}/edit', [RecordEditController::class, 'form']);
    Route::post('/records/{module}/{id}/edit', [RecordEditController::class, 'submit']);
    Route::get('/records/{module}/new', [P::class, 'form']);
    Route::post('/records/{module}', [P::class, 'store']);
    Route::get('/records/{module}/export', [P::class, 'export']);
    Route::get('/records/{module}/{id}', [P::class, 'show']);
    Route::post('/records/{module}/{id}', [P::class, 'action']);
    Route::post('/assets/{id}/acknowledge', [P::class, 'acknowledge']);
});

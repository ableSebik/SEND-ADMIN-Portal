<?php

namespace App\Http\Controllers;

use App\Models\AdminRecord;
use App\Models\VendorBankAccount;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function bank(Request $r, int $id)
    {
        abort_unless(in_array(Auth::user()->role, ['super_admin', 'admin']), 403);
        $record = Access::scope(AdminRecord::where('module', 'vendors'), Auth::user())->findOrFail($id);
        $v = $r->validate(['bank_name' => 'required|string|max:100', 'account_name' => 'required|string|max:180', 'account_number' => 'required|string|min:5|max:50', 'branch' => 'nullable|string|max:100', 'currency' => 'required|in:GHS,USD,EUR,GBP', 'current_password' => 'required|current_password']);
        unset($v['current_password']);
        DB::transaction(function () use ($record, $v) {
            AdminRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if (DB::table('approvals')->where('record_id', $record->id)->where('action', 'bank_change')->where('status', 'Pending')->exists()) {
                abort(409, 'A bank change is already pending.');
            }
            $previous = VendorBankAccount::where('record_id', $record->id)->first()?->details;
            DB::table('approvals')->insert(['record_id' => $record->id, 'action' => 'bank_change', 'payload' => json_encode(['encrypted' => Crypt::encryptString(json_encode($v)), 'previous' => Crypt::encryptString(json_encode($previous))]), 'requested_by' => Auth::id(), 'status' => 'Pending', 'created_at' => now(), 'updated_at' => now()]);
            PortalController::audit('Requested bank change', $record->reference);
        });

        return back()->with('success', 'Bank details submitted for independent approval.');
    }

    public function update(Request $r, int $id)
    {
        return app(UserManagementController::class)->save($r, $id);
    }
}

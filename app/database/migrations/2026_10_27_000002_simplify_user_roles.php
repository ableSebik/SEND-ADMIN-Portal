<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $role = User::normalizeRole($user->role);
                    if ($role !== $user->role) {
                        DB::table('users')->where('id', $user->id)->update(['role' => $role]);
                    }
                }
            });
            // Pending vehicle edits from the old workflow must be re-entered by an authorized vehicle editor.
            DB::table('approvals')->whereIn('record_id', DB::table('records')->where('module', 'vehicles')->select('id'))->where('status', 'Pending')->update(['status' => 'Superseded', 'reason' => 'Vehicle editing is now limited to Super Admin and Admin.', 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Preserve account identity and existing history instead of guessing former role assignments.
    }
};

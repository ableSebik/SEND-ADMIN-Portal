<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('users')->update(['mfa_secret' => null, 'recovery_codes' => null]);
            DB::table('records')->where('module', 'trips')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $data = json_decode($row->data, true);
                    if (! isset($data['from'])) {
                        $route = preg_split('/\s*(?:→|->)\s*/u', $row->location, 2);
                        $data['from'] = $route[0];
                        if (isset($route[1]) && ! isset($data['destination'])) {
                            $data['destination'] = $route[1];
                        }
                    }
                    $status = $row->status;
                    if ($status === 'Closed' && isset($data['closing'], $data['opening'], $data['return_at'])) {
                        $status = 'Completed';
                        $data['distance'] = round((float) $data['closing'] - (float) $data['opening'], 1);
                    } elseif (in_array($status, ['Pending review', 'Approved', 'Active', 'Closed'])) {
                        $status = 'Pending';
                    }
                    if ($status === 'Pending') {
                        if (isset($data['return_at'])) {
                            $data['legacy_expected_return_at'] = $data['return_at'];
                        }
                        unset($data['return_at'], $data['closing'], $data['distance']);
                    }
                    DB::table('records')->where('id', $row->id)->update(['status' => $status, 'data' => json_encode($data)]);
                }
            });
            DB::table('approvals')->whereIn('record_id', DB::table('records')->where('module', 'trips')->select('id'))
                ->where('status', 'Pending')->update(['status' => 'Superseded', 'reason' => 'Trip workflow changed to driver opening and closing details.', 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Do not fabricate retired MFA secrets or overwrite actual completed trip history.
    }
};

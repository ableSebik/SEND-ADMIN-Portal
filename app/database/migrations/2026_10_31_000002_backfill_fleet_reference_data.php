<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (DB::table('records')->whereIn('module', ['assets', 'trips'])->cursor() as $record) {
            $data = json_decode($record->data, true) ?? [];
            foreach (['donor', 'project'] as $field) {
                $name = trim($field === 'project' && $record->module === 'trips' ? $record->category : ($data[$field] ?? ''));
                if ($name === '' || ($field === 'project' && in_array($name, \App\Models\User::DEPARTMENTS, true))) {
                    continue;
                }
                $table = $field === 'donor' ? 'asset_donors' : 'asset_projects';
                if (DB::table($table)->where('name', $name)->exists()) {
                    continue;
                }
                $values = ['name' => $name, 'created_at' => now(), 'updated_at' => now()];
                $values += $field === 'donor' ? ['normalized_name' => mb_strtolower($name)] : ['code' => 'LEGACY-'.strtoupper(substr(hash('sha256', $name), 0, 12)), 'status' => 'Active'];
                DB::table($table)->insertOrIgnore($values);
            }
        }
    }

    public function down(): void
    {
        // Shared reference data is retained to preserve linked history.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', fn (Blueprint $t) => $t->string('unique_key')->nullable()->unique());
        foreach (DB::table('records')->where('module', 'vehicles')->get() as $r) {
            $data = json_decode($r->data, true);
            if (! empty($data['registration'])) {
                DB::table('records')->where('id', $r->id)->update(['unique_key' => 'vehicle:'.strtoupper($data['registration'])]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $t) {
            $t->dropUnique(['unique_key']);
            $t->dropColumn('unique_key');
        });
    }
};

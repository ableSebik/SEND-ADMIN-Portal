<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('asset_projects', function (Blueprint $t) {
            $t->string('funding_partner')->nullable();
            $t->text('notes')->nullable();
            $t->string('source_url', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('asset_projects', fn (Blueprint $t) => $t->dropColumn(['funding_partner', 'notes', 'source_url']));
    }
};

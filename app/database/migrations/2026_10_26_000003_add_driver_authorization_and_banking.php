<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->date('licence_expiry')->nullable());
        Schema::create('vendor_bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->unique()->constrained('records');
            $t->text('details');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bank_accounts');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('licence_expiry'));
    }
};

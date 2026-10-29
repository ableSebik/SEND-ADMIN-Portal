<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('mfa_secret')->nullable();
            $t->text('recovery_codes')->nullable();
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('records');
            $t->foreignId('uploaded_by')->constrained('users');
            $t->string('name');
            $t->string('path');
            $t->string('type');
            $t->date('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('record_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('records');
            $t->foreignId('created_by')->constrained('users');
            $t->string('type')->index();
            $t->string('title');
            $t->date('date');
            $t->decimal('amount', 14, 2)->nullable();
            $t->decimal('quantity', 12, 2)->nullable();
            $t->text('notes')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_entries');
        Schema::dropIfExists('documents');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['mfa_secret', 'recovery_codes']));
    }
};

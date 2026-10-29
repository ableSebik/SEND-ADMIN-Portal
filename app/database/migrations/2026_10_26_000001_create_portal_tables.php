<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('staff');
            $t->string('department')->default('Administration');
            $t->string('location')->default('Accra');
            $t->boolean('active')->default(true);
        });
        Schema::create('records', function (Blueprint $t) {
            $t->id();
            $t->string('module')->index();
            $t->string('reference')->unique();
            $t->string('name');
            $t->string('category');
            $t->string('department');
            $t->string('location');
            $t->string('status')->index();
            $t->json('data');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('updated_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->constrained('records');
            $t->string('action');
            $t->json('payload');
            $t->string('status')->default('Pending');
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('subject');
            $t->string('ip')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('records');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'department', 'location', 'active']));
    }
};

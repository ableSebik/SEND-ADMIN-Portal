<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('phone', 20)->nullable());
        Schema::create('vehicle_operations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained('records');
            $t->string('kind');
            $t->date('completed_on');
            $t->date('due_on')->index();
            $t->string('provider')->nullable();
            $t->string('reference')->nullable();
            $t->decimal('cost', 14, 2)->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('vehicle_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained('records');
            $t->foreignId('trip_id')->nullable()->constrained('records');
            $t->string('kind');
            $t->text('details');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('renewal_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operation_id')->constrained('vehicle_operations');
            $t->foreignId('user_id')->constrained('users');
            $t->date('scheduled_on');
            $t->string('channel');
            $t->string('status')->default('Pending');
            $t->timestamp('sent_at')->nullable();
            $t->string('error')->nullable();
            $t->timestamps();
            $t->unique(['operation_id', 'user_id', 'scheduled_on', 'channel'], 'renewal_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_deliveries');
        Schema::dropIfExists('vehicle_events');
        Schema::dropIfExists('vehicle_operations');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('phone'));
    }
};

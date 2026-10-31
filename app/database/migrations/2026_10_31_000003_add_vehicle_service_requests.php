<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_service_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained('records');
            $t->string('project');
            $t->date('requested_on');
            $t->decimal('mileage', 12, 1);
            $t->string('service_type');
            $t->text('reason');
            $t->string('provider')->nullable();
            $t->string('procurement_method');
            $t->string('diagnostic_report');
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('operation_id')->nullable()->constrained('vehicle_operations');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::table('vehicle_operations', fn (Blueprint $t) => $t->text('completion_notes')->nullable());
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_service_requests');
        Schema::table('vehicle_operations', fn (Blueprint $t) => $t->dropColumn('completion_notes'));
    }
};

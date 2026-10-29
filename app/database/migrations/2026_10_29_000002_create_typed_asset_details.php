<?php

use App\Models\AdminRecord;
use App\Services\AssetDetails;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('record_id')->unique()->constrained('records');
            $t->string('asset_type')->nullable()->index();
            $t->unsignedSmallInteger('year')->nullable();
            $t->text('description')->nullable();
            $t->string('office_id')->nullable();
            $t->date('purchase_date')->nullable();
            $t->string('supplier')->nullable();
            $t->string('origin')->nullable();
            $t->string('model')->nullable();
            $t->string('manufacturer')->nullable();
            $t->unsignedSmallInteger('manufacture_year')->nullable();
            $t->string('serial')->nullable();
            $t->string('staff_responsible')->nullable();
            $t->string('invoice')->nullable();
            $t->decimal('price', 16, 2)->nullable();
            $t->string('project')->nullable()->index();
            $t->string('donor')->nullable()->index();
            $t->timestamps();
        });
        AdminRecord::where('module', 'assets')->orderBy('id')->chunkById(100, function ($records) {
            foreach ($records as $record) {
                AssetDetails::sync($record);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_details');
    }
};

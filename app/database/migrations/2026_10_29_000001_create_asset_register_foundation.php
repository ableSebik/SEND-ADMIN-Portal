<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['asset_donors', 'asset_suppliers', 'asset_locations', 'asset_custodians'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name) {
                $t->id();
                $t->string('name');
                $t->string('normalized_name')->unique();
                $t->string('type')->nullable();
                $t->text('notes')->nullable();
                if ($name === 'asset_custodians') {
                    $t->foreignId('user_id')->nullable()->constrained('users');
                }$t->timestamps();
            });
        }
        Schema::create('asset_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignId('parent_id')->nullable()->constrained('asset_categories');
            $t->string('tag_code')->nullable();
            $t->unsignedSmallInteger('default_useful_life_years')->nullable();
            $t->string('depreciation_method')->nullable();
            $t->timestamps();
        });
        Schema::create('asset_projects', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignId('donor_id')->nullable()->constrained('asset_donors');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status')->default('Active');
            $t->timestamps();
        });
        Schema::create('asset_lookup_mappings', function (Blueprint $t) {
            $t->id();
            $t->string('field');
            $t->string('alias');
            $t->string('canonical');
            $t->unique(['field', 'alias']);
            $t->timestamps();
        });
        Schema::create('asset_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value')->nullable();
            $t->timestamps();
        });
        Schema::create('asset_import_batches', function (Blueprint $t) {
            $t->id();
            $t->string('filename');
            $t->string('checksum', 64)->index();
            $t->string('status');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->json('report')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('legacy_record_id')->nullable()->unique()->constrained('records');
            $t->string('natural_key', 64)->unique();
            $t->string('asset_tag')->nullable()->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->foreignId('category_id')->nullable()->constrained('asset_categories');
            $t->string('make')->nullable();
            $t->string('model')->nullable();
            $t->unsignedSmallInteger('year_of_manufacture')->nullable();
            $t->string('serial_or_chassis_no')->nullable()->index();
            $t->string('registration_no')->nullable();
            $t->string('origin')->nullable();
            $t->date('purchase_date')->nullable();
            $t->unsignedSmallInteger('purchase_year')->nullable()->index();
            $t->foreignId('supplier_id')->nullable()->constrained('asset_suppliers');
            $t->string('invoice_no')->nullable();
            $t->decimal('unit_cost', 16, 2)->nullable();
            $t->string('cost_basis')->nullable();
            $t->string('currency', 3)->default('GHS');
            $t->string('acquisition_type')->default('PURCHASED');
            $t->foreignId('project_id')->nullable()->constrained('asset_projects');
            $t->foreignId('donor_id')->nullable()->constrained('asset_donors');
            $t->foreignId('location_id')->nullable()->constrained('asset_locations');
            $t->foreignId('custodian_id')->nullable()->constrained('asset_custodians');
            $t->boolean('held_by_partner')->default(false);
            $t->string('status')->default('IN_USE')->index();
            $t->string('condition')->nullable();
            $t->unsignedSmallInteger('useful_life_years')->nullable();
            $t->decimal('residual_value', 16, 2)->nullable();
            $t->text('notes')->nullable();
            $t->string('source_sheet');
            $t->unsignedInteger('source_row');
            $t->foreignId('import_batch_id')->nullable()->constrained('asset_import_batches');
            $t->json('raw');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->foreignId('updated_by')->nullable()->constrained('users');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->softDeletes();
            foreach (['project_id', 'donor_id', 'location_id', 'category_id', 'custodian_id', 'supplier_id'] as $column) {
                $t->index($column);
            }
        });
        Schema::create('asset_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained('assets');
            $t->foreignId('import_batch_id')->constrained('asset_import_batches');
            $t->string('sheet');
            $t->unsignedInteger('row');
            $t->boolean('master');
            $t->json('raw');
            $t->unique(['import_batch_id', 'sheet', 'row']);
        });
        Schema::create('asset_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained('assets');
            $t->string('serial');
            $t->unique(['asset_id', 'serial']);
        });
        Schema::create('asset_import_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_batch_id')->constrained('asset_import_batches');
            $t->foreignId('asset_id')->nullable()->constrained('assets');
            $t->string('sheet');
            $t->unsignedInteger('row');
            $t->string('field')->nullable();
            $t->text('raw_value')->nullable();
            $t->string('issue_type')->index();
            $t->text('suggested_fix')->nullable();
            $t->string('resolution')->default('OPEN');
            $t->foreignId('resolved_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('asset_audit_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->nullable()->constrained('assets');
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('action');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamps();
        });
        Schema::create('asset_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained('assets');
            $t->foreignId('custodian_id')->nullable()->constrained('asset_custodians');
            $t->foreignId('location_id')->nullable()->constrained('asset_locations');
            $t->date('from_date')->nullable();
            $t->date('to_date')->nullable();
            $t->text('reason');
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('asset_tag_counters', function (Blueprint $t) {
            $t->id();
            $t->string('office');
            $t->string('category');
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('sequence')->default(0);
            $t->unique(['office', 'category', 'year']);
        });
    }

    public function down(): void
    {
        foreach (['asset_tag_counters', 'asset_assignments', 'asset_audit_log', 'asset_import_exceptions', 'asset_components', 'asset_sources', 'assets', 'asset_import_batches', 'asset_settings', 'asset_lookup_mappings', 'asset_projects', 'asset_categories', 'asset_custodians', 'asset_locations', 'asset_suppliers', 'asset_donors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

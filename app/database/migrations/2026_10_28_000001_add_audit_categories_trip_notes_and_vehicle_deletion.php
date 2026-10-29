<?php

use App\Services\AuditCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('category')->default('system')->index();
            $table->index('created_at');
        });
        Schema::create('trip_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('records');
            $table->foreignId('user_id')->constrained('users');
            $table->text('note');
            $table->timestamps();
        });
        DB::table('audit_events')->orderBy('id')->chunkById(200, function ($events) {
            foreach ($events as $event) {
                DB::table('audit_events')->where('id', $event->id)->update(['category' => AuditCategory::resolve($event->action, $event->subject)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_notes');
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn('category');
        });
        Schema::table('records', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};

<?php

use App\Enums\LeadStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // The order of a lead inside its stage column on the Pipeline board (0 = top).
            $table->unsignedInteger('pipeline_position')->default(0)->after('stage');
            // When the stage last changed. Old leads keep NULL: their history is unknown.
            $table->timestamp('stage_changed_at')->nullable()->after('pipeline_position');

            $table->index(['stage', 'pipeline_position']);
        });

        // Give every existing column a stable, gap-free order: newest first.
        foreach (LeadStage::cases() as $stage) {
            $ids = DB::table('leads')
                ->where('stage', $stage->value)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id');

            foreach ($ids->values() as $position => $id) {
                DB::table('leads')->where('id', $id)->update(['pipeline_position' => $position]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['stage', 'pipeline_position']);
            $table->dropColumn(['pipeline_position', 'stage_changed_at']);
        });
    }
};

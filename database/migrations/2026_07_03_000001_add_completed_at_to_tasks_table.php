<?php

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
        Schema::table('tasks', function (Blueprint $table) {
            // When the task entered the Done column. Drives the Done column's
            // date grouping ("Today", "Yesterday", …). Distinct from `date`,
            // which is the task's work-day and feeds the reporting widgets.
            $table->timestamp('completed_at')->nullable()->after('position');
        });

        // Seed existing Done tasks so they group under their historical day
        // instead of collapsing into a single "unknown" bucket.
        $doneColumnId = DB::table('columns')->where('name', 'Done')->value('id');

        if ($doneColumnId) {
            DB::table('tasks')
                ->where('board_column_id', $doneColumnId)
                ->whereNull('completed_at')
                ->whereNotNull('date')
                ->update(['completed_at' => DB::raw('date')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};

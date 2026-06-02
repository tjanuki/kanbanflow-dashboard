<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Locally-created tasks won't have a KanbanFlow id.
            $table->string('kanbanflow_task_id')->nullable()->change();

            $table->foreignId('board_column_id')->nullable()->after('column_id')
                ->constrained('columns')->nullOnDelete();
            $table->unsignedInteger('position')->default(0)->after('board_column_id');
        });

        // Allow new tasks to be created without pre-supplying time totals.
        Schema::table('tasks', function (Blueprint $table) {
            $table->bigInteger('total_seconds_spent')->default(0)->change();
            $table->bigInteger('total_seconds_estimate')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_column_id');
            $table->dropColumn('position');
        });
    }
};

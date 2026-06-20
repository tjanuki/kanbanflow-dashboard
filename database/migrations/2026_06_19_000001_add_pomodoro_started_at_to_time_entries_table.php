<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a pomodoro continues onto a different task mid-block, the new entry
     * starts "now" (so each task is credited the minutes worked on it) but the
     * countdown must keep ticking from when the block first began. This column
     * anchors that countdown; null means "use started_at" (a fresh block).
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dateTime('pomodoro_started_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn('pomodoro_started_at');
        });
    }
};

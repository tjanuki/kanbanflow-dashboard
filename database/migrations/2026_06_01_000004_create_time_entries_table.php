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
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('pomodoro'); // pomodoro | stopwatch | manual
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable(); // null => running ("pending")
            $table->unsignedInteger('seconds')->default(0); // filled on stop
            $table->timestamps();

            $table->index(['task_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};

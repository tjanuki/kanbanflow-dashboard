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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('kanbanflow_task_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color');
            $table->string('column_id');
            $table->bigInteger('total_seconds_spent');
            $table->bigInteger('total_seconds_estimate');
            $table->text('changed_properties')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

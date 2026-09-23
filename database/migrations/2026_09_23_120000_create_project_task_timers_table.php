<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Time spent on project tasks.
 *
 * The API's starttasktimer and endtasktimer said a timer had started and
 * stopped while nothing kept any time. A task can have many timers - one per
 * sitting, per member of staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['project_task_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_timers');
    }
};

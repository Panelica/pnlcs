<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each scheduled task last ran, and how it went.
 *
 * The automation screen listed eight tasks with the words "Never" and "Not
 * configured" typed into the template by hand — the same eight, the same
 * words, on every installation, whether the scheduler was running or not.
 * Ours runs every minute and the screen still said it had never run, so the
 * one page whose job is to tell you the automation is alive was the page you
 * could not believe.
 *
 * One row per command, overwritten on each run: the history is not what the
 * operator is asking for, only the answer to "did it run, and did it work".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 191)->unique();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('runtime_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->unsignedInteger('failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};

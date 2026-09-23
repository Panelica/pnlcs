<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTaskTimer extends Model
{
    protected $fillable = ['project_task_id', 'admin_id', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function task()
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    /** Seconds on the clock: to now while it is still running. */
    public function seconds(): int
    {
        return (int) $this->started_at->diffInSeconds($this->ended_at ?? now(), true);
    }
}

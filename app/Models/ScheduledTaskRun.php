<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTaskRun extends Model
{
    protected $fillable = [
        'command', 'last_run_at', 'runtime_ms', 'exit_code', 'last_failed_at', 'failures',
    ];

    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }

    /**
     * The scheduler describes a task by its whole command line, including the
     * php binary and the artisan path — which differ between the web user and
     * cron and would produce two rows for one task. Only the artisan command
     * itself identifies it.
     */
    public static function key(?string $summary): string
    {
        $summary = trim((string) $summary);

        if (preg_match('/artisan[\'"]?\s+(\S+)/', $summary, $m)) {
            return $m[1];
        }

        return mb_substr($summary, 0, 191);
    }
}

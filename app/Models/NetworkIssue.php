<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NetworkIssue extends Model {
    use HasFactory;

    protected $table = "network_issues";
    protected $fillable = ["title", "description", "type", "status", "priority", "affected_server", "start_date", "end_date", "last_updated"];

    protected function casts(): array
    {
        return [
            "start_date"   => "datetime",
            "end_date"     => "datetime",
            "last_updated" => "datetime",
        ];
    }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NetworkIssue extends Model {
    protected $table = "network_issues";
    protected $fillable = ["title", "description", "type", "status", "priority", "affected_server", "start_date", "end_date", "last_updated"];

}

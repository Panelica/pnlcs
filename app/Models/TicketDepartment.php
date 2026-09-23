<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketDepartment extends Model {
    use HasFactory;

    protected $fillable = ["name", "description", "email", "clients_only", "hidden", "sort_order", "feedback_request", "import_active", "import_protocol", "import_host", "import_port", "import_encryption", "import_username", "import_password", "import_folder", "import_delete", "import_allow_unknown", "last_import_at"];
    /**
     * The mailbox password is decrypted by its cast and was serialised with
     * every department - getsupportdepartments, and every ticket loaded with
     * its department (gettickets, getticket, the MCP get_ticket tool) - so
     * anyone allowed to read tickets received the support mailbox's password.
     */
    protected $hidden = ["import_password"];
    protected function casts(): array { return ["clients_only" => "boolean", "hidden" => "boolean", "feedback_request" => "boolean", "import_active" => "boolean", "import_delete" => "boolean", "import_allow_unknown" => "boolean", "import_password" => "encrypted", "last_import_at" => "datetime"]; }
    public function tickets() { return $this->hasMany(Ticket::class, "department_id"); }
}

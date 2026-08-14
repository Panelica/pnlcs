<?php
namespace App\Models;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model {
    protected $fillable = ["type", "rel_id", "field_name", "field_type", "description", "field_options", "regex", "admin_only", "required", "sort_order", "show_on_invoice", "show_on_order"];
    protected function casts(): array { return ["admin_only" => "boolean", "required" => "boolean", "show_on_invoice" => "boolean", "show_on_order" => "boolean"]; }
    public function values() { return $this->hasMany(CustomFieldValue::class, "field_id"); }

    /** Fields shown on the admin/client screens, in display order. */
    public static function clientFields()
    {
        return static::where("type", "client")->orderBy("sort_order")->orderBy("id");
    }

    /** Value saved against a given client (rel_id), if any. */
    public function valueFor($clientId): ?string
    {
        $value = $this->values()->where("rel_id", $clientId)->first();

        return $value?->value;
    }

    /** Select/checkbox options split on newlines. */
    public function options(): array
    {
        return array_values(array_filter(array_map("trim", preg_split('/\r?\n/', (string) $this->field_options))));
    }

    /**
     * The custom fields flagged "show on invoice", with the client's saved
     * values — the data that gets frozen onto an issued invoice.
     *
     * @return array<string, mixed> Field name => value.
     */
    public static function invoiceSnapshot(?Client $client): array
    {
        if (! $client) {
            return [];
        }

        $snapshot = [];

        static::where('type', 'client')
            ->where('show_on_invoice', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (self $field) use (&$snapshot, $client) {
                $value = $field->valueFor($client->id);

                if ($value !== null && $value !== '') {
                    $snapshot[$field->field_name] = $value;
                }
            });

        return $snapshot;
    }
}

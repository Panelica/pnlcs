<?php
namespace App\Models;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CustomField extends Model {
    protected $fillable = ["type", "rel_id", "field_name", "field_type", "description", "field_options", "regex", "admin_only", "required", "sort_order", "show_on_invoice", "show_on_order"];
    protected function casts(): array { return ["admin_only" => "boolean", "required" => "boolean", "show_on_invoice" => "boolean", "show_on_order" => "boolean"]; }
    public function values() { return $this->hasMany(CustomFieldValue::class, "field_id"); }

    /** Fields shown on the admin/client screens, in display order. */
    public static function clientFields()
    {
        return static::where("type", "client")->orderBy("sort_order")->orderBy("id");
    }

    /**
     * A product's own questions, asked on its order form (an operating system,
     * a site to migrate, a licence's domain); the answers are kept against the
     * service the order creates (rel_id = service id).
     */
    public static function productFields(int $productId)
    {
        return static::where('type', 'product')->where('rel_id', $productId)->orderBy('sort_order')->orderBy('id');
    }

    /** Fields a new customer is asked for while ordering ("show on order form"). */
    public static function orderFields()
    {
        return static::clientFields()->where('admin_only', false)->where('show_on_order', true);
    }

    /**
     * Validation rules for submitted custom_fields.{id} values.
     *
     * @param  iterable<self>  $fields
     * @return array{0: array<string, array<int, mixed>>, 1: array<string, string>} rules and attribute names
     */
    public static function rulesFor(iterable $fields): array
    {
        $rules = [];
        $names = [];

        foreach ($fields as $field) {
            $key = "custom_fields.{$field->id}";
            $rule = [$field->required ? 'required' : 'nullable'];

            $rule = array_merge($rule, match ($field->field_type) {
                'select' => [Rule::in($field->options())],
                'number' => ['numeric'],
                'date' => ['date'],
                'checkbox' => ['in:1'],
                default => ['string', 'max:1000'],
            });

            $rules[$key] = $rule;
            $names[$key] = $field->field_name;
        }

        return [$rules, $names];
    }

    /**
     * Save submitted values for these fields against an account; an empty
     * value removes the saved one.
     *
     * @param  iterable<self>  $fields
     * @param  array<int|string, mixed>  $input  custom_fields as submitted, keyed by field id
     */
    public static function storeValues(int $clientId, iterable $fields, array $input): void
    {
        foreach ($fields as $field) {
            $raw = $input[$field->id] ?? null;
            $value = is_array($raw) ? implode(', ', array_filter($raw)) : (string) $raw;

            if ($value === '') {
                CustomFieldValue::where('field_id', $field->id)->where('rel_id', $clientId)->delete();

                continue;
            }

            CustomFieldValue::updateOrCreate(
                ['field_id' => $field->id, 'rel_id' => $clientId],
                ['value' => $value]
            );
        }
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

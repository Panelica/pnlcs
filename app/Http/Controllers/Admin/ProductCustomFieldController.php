<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * A product's own questions (type "product", rel_id = product id), asked on
 * its order form and answered per service. The custom_fields table has always
 * had the type and rel_id columns; only client fields were ever built.
 */
class ProductCustomFieldController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $v = $request->validate([
            'field_name' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'in:text,textarea,select,checkbox,number,date'],
            'description' => ['nullable', 'string', 'max:255'],
            'field_options' => ['nullable', 'string', 'max:1000', 'required_if:field_type,select'],
            'required' => ['nullable', 'boolean'],
            'admin_only' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        CustomField::create([
            'type' => 'product',
            'rel_id' => $product->id,
            'field_name' => $v['field_name'],
            'field_type' => $v['field_type'],
            'description' => $v['description'] ?? null,
            'field_options' => $v['field_options'] ?? null,
            'required' => (bool) ($v['required'] ?? false),
            'admin_only' => (bool) ($v['admin_only'] ?? false),
            'sort_order' => (int) ($v['sort_order'] ?? 0),
        ]);

        return redirect(route('admin.products.edit', $product).'#product-fields')->with('success', __('admin.products.fields_saved'));
    }

    public function destroy(Product $product, CustomField $field)
    {
        abort_unless($field->type === 'product' && (int) $field->rel_id === (int) $product->id, 404);
        // custom_field_values.field_id cascades: the answers go with it.
        $field->delete();

        return redirect(route('admin.products.edit', $product).'#product-fields')->with('success', __('admin.products.fields_deleted'));
    }

    /** Staff correct or fill in the answers on a service. */
    public function updateService(Request $request, Service $service)
    {
        $fields = CustomField::productFields((int) $service->product_id)->get();
        [$rules, $names] = CustomField::rulesFor($fields);
        $values = $rules === [] ? [] : (array) ($request->validate($rules, [], $names)['custom_fields'] ?? []);
        CustomField::storeValues($service->id, $fields, $values);

        return back()->with('success', __('admin.products.fields_saved'));
    }
}

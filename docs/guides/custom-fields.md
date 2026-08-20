# Custom Client Fields

Collect extra information about your clients — a tax ID such as **NIP**, PESEL,
a company VAT number, a billing reference, or anything else your business needs.

Custom client field definitions live in **Configuration → Custom Fields** and
are shown when staff create or edit a client. Saved values are displayed on the
client's summary page.

## Add a field

1. **Configuration → Custom Fields → Add Custom Field**
2. Fill in the field definition (see below).
3. Save.

The new field immediately appears on the client create/edit forms.

## Field settings

| Setting | Description |
|---------|-------------|
| **Field Name** | The label shown on the form (e.g. `NIP`). |
| **Field Type** | How the field is entered: Text, Long Text, Dropdown, Checkbox, Number or Date. |
| **Field Options** | One option per line, used by the **Dropdown** type. |
| **Regex Validation** | HTML `pattern` restricting the value — e.g. `^\d{10}$` for a 10-digit NIP. Enter it **without** surrounding slashes. |
| **Description** | Optional helper text. |
| **Sort Order** | Lower numbers first in the form. |
| **Required** | The client cannot be saved without this field filled in. |
| **Admin only** | Reserved for staff; not shown on the client-facing area. |
| **Show on order** | Mark for future order-form integration. |
| **Show on invoice** | Mark for future invoice display. |

!!! tip "NIP example"
    For a Polish tax ID use `^\d{10}$`, or `^(\d{3}-\d{3}-\d{2}-\d{2}|\d{10})$`
    if you also want to accept typographic dashes (123-456-78-90).

## Editing, ordering and deleting

- **Edit** a field with the edit button in the row and change any setting.
- **Reorder** by setting **Sort Order** and going back — fields are shown in
  ascending order.
- **Delete** a field (with confirmation). Deleting a field also removes every
  saved value, both from the admin and the database.

## Where values are used

- **Admin → Clients → Create / Edit Client** renders each field according to
  its type (checkbox, dropdown options, date/number input, regex validation);
  `Required` fields must be filled before the client can be saved.
- **Admin → Clients → Client (summary tab)** lists the client's custom field
  values in a "Custom Fields" panel.

## Implementation notes

This feature is delivered as part of PR
[#8](https://github.com/Panelica/pnlcs/pull/8) ("Polish translation +
invoice builder improvements + custom client fields").

| Area | Details |
|------|---------|
| **Tables** | `custom_fields` (type `client`, rel_id `0`, field_name, field_type, field_options, regex, admin_only, required, sort_order, show_on_invoice, show_on_order) and `custom_field_values` (field_id, rel_id, value). |
| **Config controller** | `ConfigController` — `customFields`, `storeCustomField`, `updateCustomField`, `destroyCustomField` under the `config.` route group. |
| **Routes** | `admin/config/custom-fields*`, guarded by the `manage_settings` permission. |
| **Model** | `CustomField` — scopes, `clientFields()`, `valueFor($clientId)`, `options()`; `CustomFieldValue` stores one row per client and field. |
| **Views** | `resources/views/admin/config/custom-fields.blade.php` (CRUD), plus the client create/edit/show forms. |
| **Persistence** | `ClientController::saveCustomFieldValues()` uses `updateOrCreate` so a blank field clears the stored value. |
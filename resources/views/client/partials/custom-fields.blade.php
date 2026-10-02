{{--
     The client custom fields a customer fills in: the profile, and the
     checkout for fields flagged "show on order form".
     $fields   CustomField collection to render
     $clientId the account whose saved values prefill them (null: a new one)
--}}
<div class="form-grid-2">
    @foreach($fields as $field)
    @php($value = old("custom_fields.{$field->id}", $field->valueFor($clientId)))
    <div class="form-group" @if($field->field_type === 'textarea') style="grid-column:span 2;" @endif>
        <label class="form-label" for="custom_field_{{ $field->id }}">{{ $field->field_name }}@if($field->required)<span class="req">*</span>@endif</label>
        @if($field->field_type === 'textarea')
            <textarea id="custom_field_{{ $field->id }}" name="custom_fields[{{ $field->id }}]" rows="3" class="form-control" @if($field->required) required @endif>{{ $value }}</textarea>
        @elseif($field->field_type === 'select')
            <select id="custom_field_{{ $field->id }}" name="custom_fields[{{ $field->id }}]" class="form-control" @if($field->required) required @endif>
                <option value="">{{ __('common.none') }}</option>
                @foreach($field->options() as $opt)
                <option value="{{ $opt }}" @if($value === $opt) selected @endif>{{ $opt }}</option>
                @endforeach
            </select>
        @elseif($field->field_type === 'checkbox')
            <div style="padding-top:6px;">
                <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
                    <input type="checkbox" id="custom_field_{{ $field->id }}" name="custom_fields[{{ $field->id }}]" value="1" @if($value) checked @endif> {{ __('admin.custom_fields.checkbox_yes') }}
                </label>
            </div>
        @elseif($field->field_type === 'number')
            <input type="number" id="custom_field_{{ $field->id }}" name="custom_fields[{{ $field->id }}]" value="{{ $value }}" class="form-control" @if($field->required) required @endif>
        @elseif($field->field_type === 'date')
            <input type="date" id="custom_field_{{ $field->id }}" name="custom_fields[{{ $field->id }}]" value="{{ $value }}" class="form-control" @if($field->required) required @endif>
        @else
            <input type="text" id="custom_field_{{ $field->id }}" name="custom_fields[{{ $field->id }}]" value="{{ $value }}" class="form-control" @if($field->regex) pattern="{{ $field->regex }}" @endif @if($field->required) required @endif>
        @endif
        @error("custom_fields.{$field->id}")<div class="text-danger text-sm">{{ $message }}</div>@enderror
    </div>
    @endforeach
</div>

@props([
    'action' => '''',
    'message' => 'Are you sure you want to delete this item? This action cannot be undone.',
    'label' => 'Delete',
    'buttonClass' => 'btn btn-danger btn-xs',
])

<span x-data="{ confirming: false }" style="display:inline-flex; align-items:center; gap:6px;">
    <button x-show="!confirming" x-on:click="confirming = true" type="button" class="{{ $buttonClass }}">
        @if($slot->isNotEmpty()){{ $slot }}@else{{ $label }}@endif
    </button>
    <span x-show="confirming" x-transition style="display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap;">
        <span style="font-size:12px; color:#a94442;">{{ $message }}</span>
        <form method="POST" action="{{ $action }}" style="display:inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-xs">Confirm</button>
        </form>
        <button x-on:click="confirming = false" type="button" class="btn btn-default btn-xs">Cancel</button>
    </span>
</span>

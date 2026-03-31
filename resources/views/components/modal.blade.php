@props([
    'name' => 'modal',
    'title' => '',
    'maxWidth' => 'md',
])

@php
$widthMap = ['sm' => '400px', 'md' => '520px', 'lg' => '640px', 'xl' => '800px', '2xl' => '960px'];
$maxW = $widthMap[$maxWidth] ?? '520px';
@endphp

<div id="modal-{{ $name }}"
    style="display:none; position:fixed; inset:0; z-index:1050; overflow-y:auto;"
    onclick="if(event.target===this) closeModal('{{ $name }}')">
    <div style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; background:rgba(0,0,0,0.5);">
        <div class="modal-content" style="max-width:{{ $maxW }}; width:100%;" onclick="event.stopPropagation()">
            @if($title)
            <div class="modal-header">
                <h3>{{ $title }}</h3>
                <button type="button" onclick="closeModal('{{ $name }}')" style="background:none; border:none; font-size:18px; color:#999; cursor:pointer; padding:0; line-height:1;">&times;</button>
            </div>
            @endif
            <div class="modal-body">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function openModal(name) { document.getElementById('modal-'+name).style.display='block'; document.body.style.overflow='hidden'; }
function closeModal(name) { document.getElementById('modal-'+name).style.display='none'; document.body.style.overflow=''; }
document.addEventListener('keydown', function(e) { if(e.key==='Escape') { document.querySelectorAll('[id^=modal-]').forEach(function(m){m.style.display='none';}); document.body.style.overflow=''; }});
</script>
@endpush
@endonce

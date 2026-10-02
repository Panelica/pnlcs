@extends('admin.layouts.app')
@section('title', __('admin.predefined.title'))
@section('content')

<div class="page-header"><h1>{{ __('admin.predefined.title') }}</h1></div>
<p style="color:#777;font-size:13px;margin-top:-6px;">{{ __('admin.predefined.intro') }}</p>

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:15px;"><ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div style="display:grid;grid-template-columns:minmax(220px,1fr) 3fr;gap:15px;align-items:start;">
    <div class="card">
        <div class="card-header"><strong>{{ __('admin.predefined.categories') }}</strong></div>
        <div class="card-body">
            @forelse($categories as $category)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:13px;">
                <span>{{ $category->name }} <span style="color:#999;">({{ $category->replies->count() }})</span></span>
                <form method="POST" action="{{ route('admin.config.predefined-replies.categories.destroy', $category) }}" style="margin:0;" onsubmit="return confirm('{{ __('admin.predefined.confirm_delete_category') }}')">@csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button></form>
            </div>
            @empty
            <p style="color:#999;font-size:13px;margin:0 0 8px;">{{ __('admin.predefined.no_categories') }}</p>
            @endforelse
            <form method="POST" action="{{ route('admin.config.predefined-replies.categories.store') }}" style="margin-top:10px;display:flex;gap:6px;">
                @csrf
                <input type="text" name="name" required maxlength="255" class="form-control input-sm" placeholder="{{ __('admin.predefined.new_category') }}">
                <button type="submit" class="btn btn-default btn-sm">{{ __('common.actions.add') }}</button>
            </form>
        </div>
    </div>

    <div>
        @if($categories->isNotEmpty())
        <div class="card" style="margin-bottom:15px;">
            <div class="card-header"><strong>{{ __('admin.predefined.add_reply') }}</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.config.predefined-replies.store') }}">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:10px;">
                        <select name="category_id" class="form-control" required>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                        <input type="text" name="name" required maxlength="255" class="form-control" placeholder="{{ __('admin.predefined.reply_name') }}">
                    </div>
                    <textarea name="reply" rows="5" required class="form-control" style="margin-top:10px;" placeholder="{{ __('admin.predefined.reply_text') }}"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">{{ __('common.actions.save') }}</button>
                </form>
            </div>
        </div>
        @endif

        @foreach($categories as $category)
            @foreach($category->replies as $reply)
            <details class="card" style="margin-bottom:8px;">
                <summary class="card-header" style="cursor:pointer;"><strong>{{ $reply->name }}</strong> <span style="color:#999;font-size:12px;">· {{ $category->name }}</span></summary>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.config.predefined-replies.update', $reply) }}">
                        @csrf @method('PUT')
                        <div style="display:grid;grid-template-columns:1fr 2fr;gap:10px;">
                            <select name="category_id" class="form-control">@foreach($categories as $c)<option value="{{ $c->id }}" @selected($c->id === $reply->category_id)>{{ $c->name }}</option>@endforeach</select>
                            <input type="text" name="name" value="{{ $reply->name }}" required maxlength="255" class="form-control">
                        </div>
                        <textarea name="reply" rows="5" required class="form-control" style="margin-top:10px;">{{ $reply->reply }}</textarea>
                        <div style="display:flex;gap:6px;margin-top:8px;">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.config.predefined-replies.destroy', $reply) }}" style="margin-top:6px;" onsubmit="return confirm('{{ __('admin.predefined.confirm_delete_reply') }}')">@csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button></form>
                </div>
            </details>
            @endforeach
        @endforeach
    </div>
</div>
@endsection

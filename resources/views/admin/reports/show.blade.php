@extends("admin.layouts.app")
@section("title", $title)
@section("content")
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ $title }}</h1>
    <a href="{{ route("admin.reports.index") }}" class="btn btn-default btn-sm">&larr; Reports</a>
</div>
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                @foreach($columns as $col)<th>{{ $col }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
            <tr>
                @php
                    $arr = is_array($row) ? $row : $row->toArray();
                    $keys = array_keys($arr);
                    $vals = array_values($arr);
                    $moneyKeys = ['income','refunds','net','total_revenue','revenue','amount','amount_in','amount_out'];
                @endphp
                @foreach($vals as $idx => $val)
                    <td>
                        @if(is_array($val) || is_object($val))
                            -
                        @elseif(isset($keys[$idx]) && in_array($keys[$idx], $moneyKeys))
                            ${{ number_format((float)$val, 2) }}
                        @else
                            {{ $val }}
                        @endif
                    </td>
                @endforeach
            </tr>
            @empty
            <tr><td colspan="{{ count($columns) }}" style="text-align:center;color:#999;padding:30px;">No data available.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

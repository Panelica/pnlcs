@extends('install.layout', ['step' => 'finish'])
@section('title', 'Done')
@section('content')
    <div class="text-center py-6">
        <div class="w-16 h-16 mx-auto rounded-full bg-green-100 text-green-600 flex items-center justify-center text-3xl mb-4">✓</div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Installation Complete</h2>
        <p class="text-slate-600 mb-6">PNLCS is ready. The install wizard has been disabled.</p>

        <div class="bg-slate-50 border border-slate-200 rounded p-4 max-w-md mx-auto mb-6 text-left text-sm">
            <div class="text-slate-700 font-semibold mb-1">Next steps:</div>
            <ul class="text-slate-600 space-y-1">
                <li>1. Login as <code class="bg-white px-2 py-0.5 rounded border border-slate-200">{{ $username }}</code></li>
                <li>2. Configure SMTP for email delivery</li>
                <li>3. Set up at least one payment gateway</li>
                <li>4. Add server &amp; registrar plugins</li>
            </ul>
        </div>

        <a href="/admin/login" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded hover:bg-blue-700">
            Go to Admin Login →
        </a>
    </div>
@endsection

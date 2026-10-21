<?php

use App\Models\Client;
use App\Models\User;

/*
 * A view that extends a layout prints only its sections. Anything written
 * outside them - a <script> after the last @endsection - is printed before the
 * layout, ahead of <!DOCTYPE html>: the browser opens the page in quirks mode
 * and runs the script before the page it works on exists.
 *
 * The profile page lost its scripts that way (#210): changing the sign-in
 * address never opened the current password field, and the field stayed
 * read-only, so no customer could change their email address. The add-funds
 * page lost its currency conversion the same way.
 */

/**
 * What a template prints outside its sections and stacks, with comments,
 *
 * @php blocks and the directives that print nothing taken out.
 */
function outsideSections(string $template): string
{
    $text = preg_replace('/\{\{--.*?--\}\}/s', '', $template);
    $text = preg_replace('/@php\b(?!\s*\().*?@endphp/s', '', $text);

    $args = '(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?';
    preg_match_all('/@(section|push|prepend|endsection|endpush|endprepend|stop|show|overwrite|append)\b'.$args.'/', $text, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    $outside = '';
    $depth = 0;
    $from = 0;
    foreach ($tags as $tag) {
        [$whole, $at] = $tag[0];
        if ($depth === 0) {
            $outside .= substr($text, $from, $at - $from);
        }
        $name = $tag[1][0];
        $call = $tag[2][0] ?? '';
        if (in_array($name, ['section', 'push', 'prepend'], true)) {
            // @section('title', 'Text') is a whole section on one line.
            if (! ($name === 'section' && preg_match('/^\s*\(\s*([\'"]).*?\1\s*,/s', $call))) {
                $depth++;
            }
        } else {
            $depth = max(0, $depth - 1);
        }
        $from = $at + strlen($whole);
    }
    if ($depth === 0) {
        $outside .= substr($text, $from);
    }

    $outside = preg_replace('/@(extends|php|use|props)'.$args.'/', '', $outside);

    return trim($outside);
}

test('no view that extends a layout prints anything outside its sections', function () {
    $files = array_merge(
        iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))), '/\.blade\.php$/'), false),
        iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('themes'))), '/\.blade\.php$/'), false),
        iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('modules'))), '/\.blade\.php$/'), false),
    );

    $stray = [];
    foreach (array_unique(array_map('strval', $files)) as $file) {
        $template = file_get_contents($file);
        if (! preg_match('/^\s*@extends\s*\(/m', $template)) {
            continue;
        }
        if (($left = outsideSections($template)) !== '') {
            $stray[] = str_replace(base_path().'/', '', $file).': '.mb_substr(preg_replace('/\s+/', ' ', $left), 0, 60);
        }
    }

    expect($stray)->toBe([]);
});

test('the scan sees a script left after the last section, and nothing in a correct view', function () {
    expect(outsideSections("@extends('x')\n@section('title', 'T')\n@section('content')\n<p>a</p>\n@endsection\n<script>b()</script>\n"))
        ->toBe('<script>b()</script>')
        ->and(outsideSections("@extends('x')\n{{-- note --}}\n@php \$a = 1; @endphp\n@section('content')\n<p>a</p>\n@endsection\n@section('scripts')\n<script>b()</script>\n@endsection\n@push('scripts')\n<script>c()</script>\n@endpush\n"))
        ->toBe('');
});

function viewOutsideCustomer(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->clients()->attach(Client::factory()->create(['country' => 'GB'])->id, ['owner' => true]);

    return $user;
}

test('the profile page starts with its doctype, and its scripts come after the form they work on', function () {
    $html = $this->actingAs(viewOutsideCustomer())->get(route('client.account.profile'))->assertOk()->getContent();

    expect(ltrim($html))->toStartWith('<!DOCTYPE html>')
        ->and(strpos($html, "querySelector('[data-eposta-onay]')"))->toBeGreaterThan(strpos($html, 'id="current_password"'))
        ->and(strpos($html, "querySelectorAll('[data-oto-doldurma-yok]')"))->toBeGreaterThan(strpos($html, 'id="current_password"'));
});

test('the add-funds page starts with its doctype, and its scripts come after the form', function () {
    $html = $this->actingAs(viewOutsideCustomer())->get(route('client.funds.index'))->assertOk()->getContent();

    expect(ltrim($html))->toStartWith('<!DOCTYPE html>')
        ->and(strpos($html, 'window.updateConversion = function'))->toBeGreaterThan(strpos($html, 'id="amount"'));
});

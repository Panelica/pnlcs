<?php

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Addons\StaffBoard\StaffBoardModule;

/**
 * The staff message board reading a table that does not exist.
 *
 * The module queries `notes`, joins `admins` on `notes.admin_id` and reads
 * `notes.client_id`. There is no `notes` table in this application and there
 * never has been: staff notes live in `client_notes`, which carries the author
 * as a plain `admin` name, has no `admin_id`, and whose `client_id` is NOT
 * NULL. Opening the board therefore does not show an empty list - it throws,
 * and the operator gets an error page.
 *
 * The pinned badge is the second half of it. An earlier escaping pass wrapped
 * `$pinBadge` in e(), but that value is markup the module wrote itself, not
 * anything a user typed, so the badge came out as visible angle brackets beside
 * the author's name. What must stay escaped is the note text and the author.
 */
function staffBoardNote(string $note, array $attributes = []): void
{
    DB::table('client_notes')->insert(array_merge([
        'client_id' => Client::factory()->create()->id,
        'admin' => 'Ada Lovelace',
        'note' => $note,
        'sticky' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

it('shows a staff note instead of throwing', function () {
    staffBoardNote('Server 4 is being rebuilt tonight');

    $html = (new StaffBoardModule)->output(Request::create('/'));

    expect($html)->toContain('Server 4 is being rebuilt tonight')
        ->and($html)->toContain('Ada Lovelace');
});

it('draws the pinned badge as a badge, not as visible angle brackets', function () {
    staffBoardNote('Read this first', ['sticky' => 1]);

    $html = (new StaffBoardModule)->output(Request::create('/'));

    expect($html)->toContain('>PINNED<')
        ->and($html)->not->toContain('&lt;span');
});

it('puts a pinned note above a newer unpinned one', function () {
    staffBoardNote('Older but pinned', ['sticky' => 1, 'created_at' => now()->subDay()]);
    staffBoardNote('Newer and ordinary');

    $html = (new StaffBoardModule)->output(Request::create('/'));

    expect(strpos($html, 'Older but pinned'))->toBeLessThan(strpos($html, 'Newer and ordinary'));
});

it('does not run a note as markup', function () {
    staffBoardNote('<script>alert(1)</script>');

    $html = (new StaffBoardModule)->output(Request::create('/'));

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('alert(1)');
});

it('does not run an author name as markup', function () {
    staffBoardNote('Ordinary note', ['admin' => '<img src=x onerror=alert(2)>']);

    $html = (new StaffBoardModule)->output(Request::create('/'));

    expect($html)->not->toContain('<img src=x onerror=alert(2)>');
});

it('still says when the board is empty', function () {
    expect((new StaffBoardModule)->output(Request::create('/')))->toContain('No staff notes yet');
});

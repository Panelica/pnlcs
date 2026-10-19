<?php

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Models\Language;

/*
 * Announcements in more than one language.
 *
 * An announcement held one title and one text, so a shop open in Turkish and
 * English told every customer about a maintenance window in the same one. Its
 * own text is the default language's; a translation holds another language's.
 */

function annLanguages(): void
{
    Language::updateOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true, 'is_default' => true]);
    Language::updateOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'native_name' => 'Türkçe', 'is_active' => true, 'is_default' => false]);
}

function annTranslated(): Announcement
{
    $a = Announcement::create(['title' => 'Planned maintenance', 'announcement' => 'The panel is down on Sunday.', 'published' => true]);
    AnnouncementTranslation::create(['announcement_id' => $a->id, 'language' => 'tr', 'title' => 'Planlı bakım', 'announcement' => 'Panel pazar günü kapalı.']);

    return $a;
}

test('a reader sees an announcement in their own language, on the list, the page and the feed', function () {
    annLanguages();
    $a = annTranslated();

    $this->get(route('client.announcements.show', $a).'?lang=tr')->assertOk()
        ->assertSee('Planlı bakım')->assertSee('Panel pazar günü kapalı.')->assertDontSee('The panel is down on Sunday.');
    $this->get(route('client.announcements.index').'?lang=tr')->assertOk()->assertSee('Planlı bakım');
    $this->get(route('client.announcements.rss').'?lang=tr')->assertOk()->assertSee('Planlı bakım');
});

test('without a translation in their language a reader sees the default text', function () {
    annLanguages();
    $a = annTranslated();

    $this->get(route('client.announcements.show', $a).'?lang=en')->assertOk()
        ->assertSee('Planned maintenance')->assertDontSee('Planlı bakım');
    expect($a->inLocale('de')->title)->toBe('Planned maintenance')
        ->and($a->fresh()->title)->toBe('Planned maintenance');
});

test('the admin writes, changes and removes a translation with the announcement', function () {
    annLanguages();
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.config.announcements.store'), [
        'title' => 'New panel', 'body' => 'It is here.', 'published' => '1',
        'translations' => ['tr' => ['title' => 'Yeni panel', 'announcement' => 'Geldi.'], 'en' => ['title' => 'Dup', 'announcement' => 'x']],
    ])->assertRedirect();
    $a = Announcement::where('title', 'New panel')->firstOrFail();
    expect($a->translations()->pluck('title', 'language')->all())->toBe(['tr' => 'Yeni panel']);

    $this->actingAs($admin, 'admin')->put(route('admin.config.announcements.update', $a), [
        'title' => 'New panel', 'body' => 'It is here.', 'published' => '1',
        'translations' => ['tr' => ['title' => 'Yepyeni panel', 'announcement' => 'Geldi.']],
    ])->assertRedirect();
    expect($a->translations()->value('title'))->toBe('Yepyeni panel');

    $this->actingAs($admin, 'admin')->put(route('admin.config.announcements.update', $a), [
        'title' => 'New panel', 'body' => 'It is here.', 'published' => '1',
        'translations' => ['tr' => ['title' => '', 'announcement' => 'left over']],
    ])->assertRedirect();
    expect($a->translations()->count())->toBe(0);
});

test('the admin page offers the other languages and deleting removes the translations', function () {
    annLanguages();
    $a = annTranslated();

    $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.config.announcements'))->assertOk()
        ->assertSee('translations[tr][title]', false)->assertDontSee('translations[en][title]', false);

    $a->delete();
    expect(AnnouncementTranslation::count())->toBe(0);
});

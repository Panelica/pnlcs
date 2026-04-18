<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Announcement;
use App\Models\KbCategory;
use App\Models\KbArticle;


// ============================================================
// Helpers
// ============================================================

function makeContentAdmin(): Admin
{
    $role = AdminRole::factory()->fullAdmin()->create();
    return Admin::factory()->create(['role_id' => $role->id]);
}

// ============================================================
// Announcements
// ============================================================

test('admin can view announcements page', function () {
    $admin = makeContentAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.announcements'))
         ->assertStatus(200);
});

test('admin can create an announcement', function () {
    $admin = makeContentAdmin();
    $response = $this->actingAs($admin, 'admin')
         ->post(route('admin.config.announcements.store'), [
             'title'        => 'Test Announcement',
             'announcement' => 'This is the body of the announcement.',
             'published'    => '1',
         ]);
    $response->assertRedirect();
    $this->assertDatabaseHas('announcements', [
        'title'     => 'Test Announcement',
        'published' => 1,
    ]);
});

test('admin can update an announcement', function () {
    $admin        = makeContentAdmin();
    $announcement = Announcement::factory()->create(['title' => 'Old Title']);
    $this->actingAs($admin, 'admin')
         ->put(route('admin.config.announcements.update', $announcement), [
             'title'        => 'New Title',
             'announcement' => 'Updated body.',
             'published'    => '0',
         ])
         ->assertRedirect();
    $this->assertDatabaseHas('announcements', ['title' => 'New Title']);
});

test('admin can delete an announcement', function () {
    $admin        = makeContentAdmin();
    $announcement = Announcement::factory()->create();
    $this->actingAs($admin, 'admin')
         ->delete(route('admin.config.announcements.destroy', $announcement))
         ->assertRedirect();
    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});

test('announcement creation validates required fields', function () {
    $admin = makeContentAdmin();
    $this->actingAs($admin, 'admin')
         ->post(route('admin.config.announcements.store'), [])
         ->assertSessionHasErrors(['title', 'announcement']);
});

// ============================================================
// Knowledge Base — Categories
// ============================================================

test('admin can view knowledge base page', function () {
    $admin = makeContentAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.knowledge-base'))
         ->assertStatus(200);
});

test('admin can create a KB category', function () {
    $admin = makeContentAdmin();
    $this->actingAs($admin, 'admin')
         ->post(route('admin.config.knowledge-base.categories.store'), [
             'name'       => 'Getting Started',
             'sort_order' => 1,
         ])
         ->assertRedirect();
    $this->assertDatabaseHas('kb_categories', ['name' => 'Getting Started']);
});

// ============================================================
// Knowledge Base — Articles
// ============================================================

test('admin can create a KB article', function () {
    $admin    = makeContentAdmin();
    $category = KbCategory::factory()->create();
    $this->actingAs($admin, 'admin')
         ->post(route('admin.config.knowledge-base.articles.store'), [
             'category_id' => $category->id,
             'title'       => 'How to get started',
             'article'     => 'This is the article content.',
         ])
         ->assertRedirect();
    $this->assertDatabaseHas('kb_articles', ['title' => 'How to get started']);
});

test('admin can update a KB article', function () {
    $admin    = makeContentAdmin();
    $category = KbCategory::factory()->create();
    $article  = KbArticle::factory()->create(['category_id' => $category->id, 'title' => 'Old Title']);
    $this->actingAs($admin, 'admin')
         ->put(route('admin.config.knowledge-base.articles.update', $article), [
             'category_id' => $category->id,
             'title'       => 'Updated Title',
             'article'     => 'Updated content.',
         ])
         ->assertRedirect();
    $this->assertDatabaseHas('kb_articles', ['title' => 'Updated Title']);
});

test('admin can delete a KB article', function () {
    $admin    = makeContentAdmin();
    $category = KbCategory::factory()->create();
    $article  = KbArticle::factory()->create(['category_id' => $category->id]);
    $this->actingAs($admin, 'admin')
         ->delete(route('admin.config.knowledge-base.articles.destroy', $article))
         ->assertRedirect();
    $this->assertDatabaseMissing('kb_articles', ['id' => $article->id]);
});

test('KB article creation validates required fields', function () {
    $admin = makeContentAdmin();
    $this->actingAs($admin, 'admin')
         ->post(route('admin.config.knowledge-base.articles.store'), [])
         ->assertSessionHasErrors(['category_id', 'title', 'article']);
});

// ============================================================
// Unauthenticated access is blocked
// ============================================================

test('unauthenticated user cannot view announcements', function () {
    $this->get(route('admin.config.announcements'))
         ->assertRedirect(route('admin.login'));
});

test('unauthenticated user cannot view knowledge base', function () {
    $this->get(route('admin.config.knowledge-base'))
         ->assertRedirect(route('admin.login'));
});

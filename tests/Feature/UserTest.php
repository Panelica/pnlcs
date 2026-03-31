<?php

use App\Models\User;


test("user migration creates table with correct columns", function () {
    $columns = Schema::getColumnListing("users");

    expect($columns)->toContain("first_name")
        ->toContain("last_name")
        ->toContain("email")
        ->toContain("second_factor_type")
        ->toContain("language")
        ->toContain("last_login")
        ->toContain("is_active");
});

test("user can be created with factory", function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->first_name)->not->toBeEmpty()
        ->and($user->last_name)->not->toBeEmpty()
        ->and($user->email)->toContain("@")
        ->and($user->is_active)->toBeTrue();
});

test("user full name accessor works", function () {
    $user = User::factory()->create([
        "first_name" => "John",
        "last_name" => "Doe",
    ]);

    expect($user->full_name)->toBe("John Doe");
});

test("user password is hashed", function () {
    $user = User::factory()->create([
        "password" => "secret123",
    ]);

    expect($user->password)->not->toBe("secret123")
        ->and(Hash::check("secret123", $user->password))->toBeTrue();
});

test("inactive user can be created", function () {
    $user = User::factory()->inactive()->create();

    expect($user->is_active)->toBeFalse();
});

test("user email must be unique", function () {
    User::factory()->create(["email" => "test@example.com"]);

    expect(fn () => User::factory()->create(["email" => "test@example.com"]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test("migration can rollback and re-run", function () {
    // This tests up/down works
    $this->artisan("migrate:rollback", ["--step" => 1])->assertExitCode(0);
    $this->artisan("migrate")->assertExitCode(0);

    expect(Schema::hasTable("users"))->toBeTrue();
});

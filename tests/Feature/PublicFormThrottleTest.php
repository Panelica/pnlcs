<?php

/**
 * The two public forms that were not counted.
 *
 * The contact form allows five posts a minute and the forgotten-password form
 * the same, because both cost something to answer. Two more did not: signing up,
 * which makes an account and sends mail, and the domain search, where a single
 * request fans out into a WHOIS query for the name and one for every suggested
 * ending - outbound connections the registries themselves throttle, made in
 * the operator's name by anybody at all.
 */
function floodPost(string $route, array $payload, int $times = 30): bool
{
    foreach (range(1, $times) as $attempt) {
        $payload['__attempt'] = $attempt;

        if (test()->post(route($route), $payload)->status() === 429) {
            return true;
        }
    }

    return false;
}

it('counts domain searches', function () {
    expect(floodPost('client.domain.check', ['domain' => 'example-search.com']))->toBeTrue();
});

it('counts sign-ups', function () {
    $attempt = 0;

    $hit = collect(range(1, 30))->contains(function () use (&$attempt) {
        $attempt++;

        return $this->post(route('client.register.submit'), [
            'first_name' => 'Flood',
            'last_name' => 'Test',
            'email' => "flood{$attempt}@test.local",
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->status() === 429;
    });

    expect($hit)->toBeTrue();
});

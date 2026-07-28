<?php

test('the test suite does not use the live queue', function () {
    expect(config('queue.default'))->toBeIn(['sync', 'array'])
        ->and(config('mail.default'))->toBeIn(['array', 'log']);
});

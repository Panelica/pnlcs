<?php

use App\Widgets\HealthWidget;

/*
 * The system health box asked for the free space on "/". Under open_basedir,
 * which shared and panel-managed hosts set, the filesystem root cannot be
 * read, so the box said "0 GB" on a disk with room to spare. It now reads the
 * disk the installation is on, and says so when even that cannot be read.
 */

test('free space is read from the disk the installation is on', function () {
    $data = (new HealthWidget)->getData();

    expect($data['disk_free_gb'])->toBe(HealthWidget::freeDiskGb(base_path()))
        ->and($data['disk_free_gb'])->toBeGreaterThan(0);
});

test('a path that cannot be read gives no figure rather than zero', function () {
    expect(HealthWidget::freeDiskGb(base_path('no-such-directory-'.uniqid())))->toBeNull();
});

test('the box says not available instead of 0 GB', function () {
    $widget = new HealthWidget;
    $data = $widget->getData();

    expect($widget->render(['disk_free_gb' => null] + $data))
        ->toContain(__('admin.dashboard.na'))->not->toContain('>0 GB<')
        ->and($widget->render($data))->toContain($data['disk_free_gb'].' GB');
});

<?php

use App\Translation\OfficialTranslationRepository;

function compareChineseTranslationNode(array $english, array $chinese, string $path, array &$issues): void
{
    if (array_keys($english) !== array_keys($chinese)) {
        $issues[] = "Key structure differs at {$path}";

        return;
    }

    foreach ($english as $key => $source) {
        $currentPath = $path.'.'.$key;
        $target = $chinese[$key];

        if (is_array($source)) {
            if (! is_array($target)) {
                $issues[] = "Array structure differs at {$currentPath}";

                continue;
            }
            compareChineseTranslationNode($source, $target, $currentPath, $issues);

            continue;
        }

        if (! is_string($target) || trim($target) === '') {
            $issues[] = "Translation is empty at {$currentPath}";

            continue;
        }

        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', (string) $source, $sourcePlaceholders);
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $target, $targetPlaceholders);
        sort($sourcePlaceholders[1]);
        sort($targetPlaceholders[1]);
        if ($sourcePlaceholders[1] !== $targetPlaceholders[1]) {
            $issues[] = "Placeholders differ at {$currentPath}";
        }

        preg_match_all('/<\/?[A-Za-z][^>]*>/', (string) $source, $sourceTags);
        preg_match_all('/<\/?[A-Za-z][^>]*>/', $target, $targetTags);
        if ($sourceTags[0] !== $targetTags[0]) {
            $issues[] = "HTML tags differ at {$currentPath}";
        }
    }
}

test('simplified Chinese mirrors every official English translation file', function () {
    $issues = [];
    $englishFiles = glob(dirname(__DIR__, 2).'/lang/en/*.php') ?: [];

    foreach ($englishFiles as $englishFile) {
        $group = basename($englishFile);
        $chineseFile = dirname(__DIR__, 2).'/lang/zh/'.$group;
        if (! is_file($chineseFile)) {
            $issues[] = "Missing lang/zh/{$group}";

            continue;
        }

        compareChineseTranslationNode(require $englishFile, require $chineseFile, $group, $issues);
    }

    expect($issues)->toBe([]);
});

test('official translation locales cannot traverse outside the language directory', function () {
    $repository = app(OfficialTranslationRepository::class);

    expect($repository->forLocale('..'))->toBe([])
        ->and($repository->forLocale('../lang/en'))->toBe([])
        ->and($repository->forLocale('zh/../en'))->toBe([]);
});

test('simplified Chinese contains representative product translations', function () {
    $root = dirname(__DIR__, 2).'/lang/zh';
    $auth = require $root.'/auth.php';
    $admin = require $root.'/admin.php';
    $client = require $root.'/client.php';
    $email = require $root.'/email.php';
    $pdf = require $root.'/pdf.php';
    $validation = require $root.'/validation.php';

    expect($auth['login']['title'])->toBe('登录')
        ->and($auth['2fa']['title'])->toBe('双因素认证')
        ->and($admin['nav.clients'])->toContain('客户')
        ->and($client['nav']['invoices'])->toContain('发票')
        ->and($email['common']['view_ticket'])->toContain('工单')
        ->and($pdf['invoice'])->toBe('发票')
        ->and($validation['required'])->toContain(':attribute');
});

test('simplified Chinese files do not delegate to English or contain translation markers', function () {
    foreach (glob(dirname(__DIR__, 2).'/lang/zh/*.php') ?: [] as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toContain('lang/en')
            ->and($contents)->not->toContain('TODO: Translate');
    }
});

test('simplified Chinese does not contain raw keys or unnecessary English sentences', function () {
    $allowedEnglish = [
        'ID', 'Laravel', 'PHP', 'PHP Mail', 'PNLCS', 'PayPal', 'SLA', 'SMTP',
        'Stripe', 'Nginx + PHP-FPM',
    ];

    $walk = function (array $values, string $group, string $prefix = '') use (&$walk, $allowedEnglish): void {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $walk($value, $group, $path);

                continue;
            }

            expect($value)->not->toBe($path);
            expect($value)->not->toBe($group.'.'.$path);
            preg_match_all('/\b[A-Za-z]{3,}\b/', strip_tags($value), $words);
            if (count($words[0]) >= 3 && ! preg_match('/\p{Han}/u', $value)) {
                expect($allowedEnglish)->toContain($value);
            }
        }
    };

    foreach (glob(dirname(__DIR__, 2).'/lang/zh/*.php') ?: [] as $file) {
        $walk(require $file, basename($file, '.php'));
    }
});

<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/helpers.php';

$GLOBALS['__assertions'] = 0;

function fail($message) {
    throw new RuntimeException($message);
}

function assert_true($condition, $label) {
    $GLOBALS['__assertions']++;
    if (!$condition) {
        fail("$label — expected truthy, got falsy");
    }
}

function assert_eq($expected, $actual, $label) {
    $GLOBALS['__assertions']++;
    if ($expected !== $actual) {
        $exp = var_export($expected, true);
        $act = var_export($actual, true);
        fail("$label — expected $exp, got $act");
    }
}

function assert_contains($needle, $haystack, $label) {
    $GLOBALS['__assertions']++;
    if (strpos($haystack, $needle) === false) {
        fail("$label — expected to contain " . var_export($needle, true));
    }
}

function assert_throws($fn, $label) {
    $GLOBALS['__assertions']++;
    try {
        $fn();
    } catch (Throwable $e) {
        return $e;
    }
    fail("$label — expected an exception, none was thrown");
}

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && substr($file->getFilename(), -9) === '.test.php') {
        $files[] = $file->getPathname();
    }
}
sort($files);

foreach ($files as $file) {
    require $file;
}

$tests = array_values(array_filter(
    get_defined_functions()['user'],
    fn($fn) => str_starts_with($fn, 'test_')
));

echo "Running " . count($tests) . " tests from " . count($files) . " files\n";
echo str_repeat('-', 60) . "\n";

$passed = 0;
$failed = 0;
$failures = [];

foreach ($tests as $test) {
    try {
        $test();
        $passed++;
        echo "  \xE2\x9C\x93 $test\n";
    } catch (Throwable $e) {
        $failed++;
        $failures[] = "$test — {$e->getMessage()} (" . basename($e->getFile()) . ":" . $e->getLine() . ")";
        echo "  \xE2\x9C\x97 $test — {$e->getMessage()}\n";
    }
}

echo str_repeat('-', 60) . "\n";
echo "{$passed} passed, {$failed} failed, {$GLOBALS['__assertions']} assertions\n";

if ($failures) {
    echo "\nFailures:\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
}

exit($failed > 0 ? 1 : 0);

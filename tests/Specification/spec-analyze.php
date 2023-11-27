<?php

namespace Ratchet\RFC6455\Test;

use Illuminate\Support\Arr;

require __DIR__.'/../../vendor/autoload.php';

$hasFailures = false;

if (! file_exists($file = __DIR__.'/reports/index.json')) {
    echo 'No test results found.'.PHP_EOL;

    exit(1);
}

$results = file_get_contents($file);
$results = Arr::first(json_decode($results, true));

foreach ($results as $name => $result) {
    if ($result['behavior'] === 'INFORMATIONAL') {
        continue;
    }

    if (in_array($result['behavior'], ['OK', 'NON-STRICT'])) {
        echo '✅ Test case '.$name.' passed.'.PHP_EOL;
    } else {
        $hasFailures = true;
        echo '❌ Test case '.$name.' failed.'.PHP_EOL;
    }
}

exit($hasFailures ? 1 : 0);

<?php
/**
 * This is the server implementation half of the MockServer server instance.
 */

use karmabunny\visor\CliRequest;

require getenv('VENDOR_PATH') . '/autoload.php';
$request = CliRequest::create();

$mocks = @file_get_contents(getcwd() . '/mocks.json');
$mocks = $mocks ? json_decode($mocks, true) : null;

if (
    is_array($mocks)
    and is_array($res = $mocks[$request->path] ?? null)
) {
    foreach ($res['headers'] as $name => $value) {
        $header = ltrim("{$name}: {$value}", ': ');
        header($header);
    }

    echo $res['body'];
}

$payload = $request->toArray();
$payload = json_encode($payload, JSON_PRETTY_PRINT);
file_put_contents(getcwd() . '/latest.json', $payload);

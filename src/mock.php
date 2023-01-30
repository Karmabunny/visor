<?php

/**
 * This is the server implementation half of the MockServer server instance.
 */
if (
    PHP_SAPI === 'cli-server'
    and get_included_files()[0] === __FILE__
) {
    require __DIR__ . '/CliRequest.php';
    $request = CliRequest::create();

    error_log("[{$request->method}] {$request->path}");

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
}

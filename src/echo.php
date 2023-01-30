<?php

/**
 * This is the server implementation half of the EchoServer server instance.
 */
if (
    PHP_SAPI === 'cli-server'
    and get_included_files()[0] === __FILE__
) {
    require __DIR__ . '/CliRequest.php';
    $request = CliRequest::create();

    error_log("[{$request->method}] {$request->path}");

    $payload = $request->toArray();
    $payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    header('Content-Type: application/json');
    echo $payload;

    file_put_contents(getcwd() . '/latest.json', $payload);
}

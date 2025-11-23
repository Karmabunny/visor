<?php
/**
 * This is the server implementation half of the EchoServer server instance.
 */

use karmabunny\visor\CliRequest;

require getenv('VENDOR_PATH') . '/autoload.php';
$request = CliRequest::create();

$payload = $request->toArray();
$payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

header('Content-Type: application/json');
echo $payload;

file_put_contents(getcwd() . '/latest.json', $payload);

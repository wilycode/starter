<?php

declare(strict_types=1);

use App\Application;
use App\Bootstrap\SessionFactory;
use App\Support\BaseUrl;
use App\Support\Environment;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require dirname(__DIR__) . '/vendor/autoload.php';

$application = new Application(dirname(__DIR__));

$factory = new Psr17Factory();
$request = new ServerRequestCreator($factory, $factory, $factory, $factory)->fromGlobals();

SessionFactory::configure(
    $request,
    new BaseUrl(Environment::get('APP_URL', 'http://localhost:8090')),
);
session_start();

$response = $application->handle($request);

http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name . ': ' . $value, false);
    }
}
if (strtoupper($request->getMethod()) !== 'HEAD') {
    echo (string) $response->getBody();
}

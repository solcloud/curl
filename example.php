<?php

if ((file_exists($path = __DIR__ . '/../../../vendor/autoload.php'))) {
    require $path;
} else {
    require __DIR__ . '/vendor/autoload.php';
}

use Solcloud\Curl\CurlRequest;
use Solcloud\Http\Request;

$curl = new CurlRequest();
$request = new Request();
$request
    ->setUrl('https://www.google.com/')
    ->setConnectionTimeoutSec(1)
    ->setRequestTimeoutSec(2)
    ->setHeaders([
        'X-header' => 'x-value',
    ])
    ->setReferer('about:blank')
    ->setUserAgent('solcloud-curl')
;
$response = $curl->fetchResponse($request);

echo "GOT {$response->getStatusCode()}: {$response->getRealUrl()}" . PHP_EOL;
echo substr($response->getBody(), 0, 30) . '...' . PHP_EOL;
print_r($response->getLastHeaders());

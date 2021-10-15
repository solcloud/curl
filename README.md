# Curl

Making http requests with ease.

## Usage

Just setup `Request` object using setters and pass is to `CurlRequest`

```php
use Solcloud\Curl\CurlRequest;
use Solcloud\Http\Request;

$curl = new CurlRequest();
$request = new Request();
$request
    ->setUrl('https://www.google.com/')
    ->setConnectionTimeoutSec(1)
    ->setRequestTimeoutSec(2)
    ->setHeaders([
        'X-header: x-value',
    ])
    ->setReferer('about:blank')
    ->setUserAgent('solcloud-curl')
;
$response = $curl->fetchResponse($request);

echo "GOT {$response->getStatusCode()}: {$response->getRealUrl()}" . PHP_EOL;
echo substr($response->getBody(), 0, 30) . '...' . PHP_EOL;
print_r($response->getLastHeaders());
```

For complete example see [example.php](example.php)

## Interface

`CurlRequest` implements `\Solcloud\Http\Contract\IRequestDownloader` interface from `solcloud/http` package

```php
public function fetchResponse(Request $request): Response;
```

Beside `CurlRequest`, there is also `FileResponse` and `StringResponse` implementations for easy _mocking_ or _caching_.

```php
/** @var \Solcloud\Http\Contract\IRequestDownloader $downloader  */
$downloader = new \Solcloud\Curl\FileResponse('/tmp/response.html');
$downloader->fetchResponse($request) // curl not needed
```

You can also use second constructor parameter `$modifyResponseCallback` for more complex behaviour.

```php
use Solcloud\Curl\FileResponse;
use Solcloud\Http\Request;
use Solcloud\Http\Response;

$downloader = new FileResponse('/tmp/response.html', function (Request $request, Response $response): void {
    if ($request->getUrl() === 'badssl') {
        throw new \Solcloud\Curl\Exception\Specific\SSLException('SSL error');
    }
    if (rand(0, 1) === 0) {
        throw new \Solcloud\Http\Exception\ResponseException('Response error');
    }
    $response->setBody($response->getBody() . ' - modified');
});
$downloader->fetchResponse($request);
```

<?php

declare(strict_types=1);

namespace Solcloud\Curl;

use Solcloud\Http\Contract\IRequestDownloader;
use Solcloud\Http\Request;
use Solcloud\Http\Response;

class StringResponse implements IRequestDownloader
{

    /**
     * @var string
     */
    protected $requestBody;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @param string        $responseBody
     * @param callable|null $modifyResponseCallback function (Request $request, Response $response): void {}
     */
    public function __construct(string $responseBody, callable $modifyResponseCallback = null)
    {
        $this->requestBody = $responseBody;
        $this->callback = $modifyResponseCallback;
    }

    public function fetchResponse(Request $request): Response
    {
        $response = new Response;
        $response->setBody($this->requestBody);
        if ($this->callback) {
            call_user_func($this->callback, $request, $response);
        }

        return $response;
    }

}

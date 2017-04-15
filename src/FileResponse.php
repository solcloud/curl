<?php

declare(strict_types=1);

namespace Solcloud\Curl;

class FileResponse extends StringResponse
{

    /**
     * @param string        $path
     * @param callable|null $modifyResponseCallback function (\Solcloud\Http\Request $request, \Solcloud\Http\Response $response): void {}
     */
    public function __construct(string $path, callable $modifyResponseCallback = null)
    {
        parent::__construct(file_get_contents($path), $modifyResponseCallback);
    }

}

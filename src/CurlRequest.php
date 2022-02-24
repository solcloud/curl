<?php

declare(strict_types=1);

namespace Solcloud\Curl;

use Solcloud\Http\Request;
use Solcloud\Http\Response;
use Solcloud\Curl\Exception\CurlException;
use Solcloud\Http\Contract\IRequestDownloader;

class CurlRequest implements IRequestDownloader
{

    private $proxyAddress = null;

    /**
     * Alias for makeRequestHttpCall() to satisfy IRequestDownloader interface
     * @param Request $request
     * @return Response
     */
    public function fetchResponse(Request $request): Response
    {
        return $this->makeRequestHttpCall($request);
    }

    /**
     * Create cUrl instance and make HTTP request based on $request object
     * @param Request $request
     * @return Response
     */
    public function makeRequestHttpCall(Request $request): Response
    {
        $response = new Response();

        $ch = $this->createCurl($request);

        $allHeaders = [];
        $headerIndex = -1;
        $lastRealUrl = null;
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, string $header) use (&$allHeaders, &$headerIndex, &$lastRealUrl) {
            $len = strlen($header);

            $realUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
            if ($realUrl !== $lastRealUrl) { // new header for redirect
                $allHeaders[++$headerIndex] = [];
                $lastRealUrl = $realUrl;
            }

            if (count(explode(':', $header, 2)) < 2) { // invalid header
                return $len;
            }

            $allHeaders[$headerIndex][] = trim($header);
            return $len;
        });

        $curlResult = $this->execute($ch);

        $response->setStatusCode(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $response->setRealUrl(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
        $response->setLastIp(curl_getinfo($ch, CURLINFO_PRIMARY_IP));

        $response->setAllHeaders($allHeaders);
        $response->setBody($curlResult);

        $this->close($ch);
        return $response;
    }

    /**
     * Execute given cUrl handle
     * @param resource $ch cUrl handle
     * @return string cUrl exec result
     * @throws CurlException or Specific subclasses
     */
    protected function execute($ch): string
    {
        $curlResult = curl_exec($ch);
        if ($curlResult === FALSE || curl_errno($ch) !== 0) {
            $errorMsg = curl_error($ch) . '; CurlInfo: ' . var_export(curl_getinfo($ch), true);
            $errorNum = curl_errno($ch);

            try {
                $this->tryThrowSpecificException($errorMsg, $errorNum);
            } catch (CurlException $ex) {
                $ex->setLastUrl(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
                $ex->setLastIP(curl_getinfo($ch, CURLINFO_PRIMARY_IP));
            }

            throw $ex;
        }

        return $curlResult;
    }

    /**
     * Gracefully close cUrl handle
     * @param resource $ch cUrl handle
     */
    protected function close($ch)
    {
        if ($ch && is_resource($ch)) {
            curl_close($ch);
        }
    }

    /**
     * Return new cUrl handle setup based on $request
     * @param Request $request
     * @return resource cUrl handle
     */
    protected function createCurl(Request $request)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request->getUrl());
        if ($request->getOutgoingIp() !== NULL) {
            curl_setopt($ch, CURLOPT_INTERFACE, $request->getOutgoingIp());
        }
        if ($request->getMethod() === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
        } else {
            curl_setopt($ch, CURLOPT_POST, TRUE);
        }
        if ($request->getPostFields() !== []) {
            if (1 === count($request->getPostFields()) && isset($request->getPostFields()[0])) { // sequential array with one value
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $request->getPostFields()[0]);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request->getPostFields()));
            }
        }
        if ($request->getBasicHTTPAuthentication() !== NULL) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $request->getBasicHTTPAuthentication());
        }

        if ($this->proxyAddress) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxyAddress);
        }
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $request->getVerifyHost() ? 2 : FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $request->getVerifyPeer() ? 2 : FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $request->getConnectionTimeoutMs());
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $request->getRequestTimeoutMs());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $request->getFollowLocation());
        curl_setopt($ch, CURLOPT_USERAGENT, $request->getUserAgent());
        if ($request->getReferer() !== NULL) {
            curl_setopt($ch, CURLOPT_REFERER, $request->getReferer());
        }

        $headers = $request->getHeaders();
        $headers[] = 'Expect:';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }

    protected function tryThrowSpecificException(string $errorMsg, int $errorNum): void
    {
        // https://curl.se/libcurl/c/libcurl-errors.html
        switch ($errorNum) {
            case 3:
                throw new Exception\Specific\UrlIllegaCharacterException($errorMsg, $errorNum);
            case 6:
                throw new Exception\Specific\CouldNotResolveHostException($errorMsg, $errorNum);
            case 7:
                throw new Exception\Specific\ConnectionRefusedException($errorMsg, $errorNum);
            case 28:
                if (strpos($errorMsg, 'Connection timed out after') === 0) {
                    throw new Exception\Specific\ConnectionTimeoutException($errorMsg, $errorNum);
                }
                throw new Exception\Specific\OperationTimeoutException($errorMsg, $errorNum);
            case 38:
            case 45:
                throw new Exception\Specific\BindAddressFailedException($errorMsg, $errorNum);
            case 52:
                throw new Exception\Specific\EmptyReplyFromServerException($errorMsg, $errorNum);
            case 56:
                throw new Exception\Specific\ReceiveFailureException($errorMsg, $errorNum);
            case 35:
            case 53:
            case 54:
            case 58:
            case 59:
            case 60:
            case 64:
            case 66:
            case 77:
            case 80:
            case 82:
            case 83:
            case 90:
            case 91:
            case 96:
            case 98:
                throw new Exception\Specific\SSLException($errorMsg, $errorNum);
        }

        throw new CurlException($errorMsg, $errorNum);
    }

    public function setProxyAddress(?string $proxyAddress): void
    {
        $this->proxyAddress = $proxyAddress;
    }

}

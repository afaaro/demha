<?php

namespace App\Modules\Shop\Services\Http;

use App\System\Library\Logger;

/**
 * Generic HTTP client for marketplace integrations.
 *
 * Supports:
 * - GET
 * - POST
 * - PUT
 * - PATCH
 * - DELETE
 * - JSON
 * - Form-urlencoded
 * - Bearer Authentication
 * - Basic Authentication
 */
class HttpClient
{
    protected ?Logger $logger;

    protected int $timeout = 30;

    protected string $userAgent = 'ShopFramework/1.0';
    protected bool $verifySsl = true;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Set timeout.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set User-Agent.
     */
    public function userAgent(string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * Toggle SSL certificate verification.
     */
    public function verifySsl(bool $verify): static
    {
        $this->verifySsl = $verify;
        return $this;
    }

    /**
     * GET request.
     */
    public function get(
        string $url,
        array $headers = []
    ): HttpResponse {

        return $this->request(
            'GET',
            $url,
            null,
            $headers
        );
    }

    /**
     * POST JSON.
     */
    public function post(
        string $url,
        array $payload = [],
        array $headers = []
    ): HttpResponse {

        $headers[] = 'Content-Type: application/json';

        return $this->request(
            'POST',
            $url,
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            ),
            $headers
        );
    }

    /**
     * POST form-urlencoded.
     */
    public function postForm(
        string $url,
        array $payload,
        array $headers = []
    ): HttpResponse {

        $headers[] = 'Content-Type: application/x-www-form-urlencoded';

        return $this->request(
            'POST',
            $url,
            http_build_query($payload),
            $headers
        );
    }

    /**
     * PUT JSON.
     */
    public function put(
        string $url,
        array $payload,
        array $headers = []
    ): HttpResponse {

        $headers[] = 'Content-Type: application/json';

        return $this->request(
            'PUT',
            $url,
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            ),
            $headers
        );
    }

    /**
     * PATCH JSON.
     */
    public function patch(
        string $url,
        array $payload,
        array $headers = []
    ): HttpResponse {

        $headers[] = 'Content-Type: application/json';

        return $this->request(
            'PATCH',
            $url,
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            ),
            $headers
        );
    }

    /**
     * DELETE.
     */
    public function delete(
        string $url,
        array $headers = []
    ): HttpResponse {

        return $this->request(
            'DELETE',
            $url,
            null,
            $headers
        );
    }

    /**
     * Bearer helper.
     */
    public function bearer(string $token): array
    {
        return [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ];
    }

    /**
     * Basic helper.
     */
    public function basic(
        string $username,
        string $password
    ): array {

        return [
            'Authorization: Basic ' .
            base64_encode($username . ':' . $password),

            'Accept: application/json'
        ];
    }

    /**
     * Core request method.
     */
    public function request(
        string $method,
        string $url,
        string|null $body = null,
        array $headers = []
    ): HttpResponse {

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return HttpResponse::failure(0, 'Invalid URL scheme. Only HTTP/HTTPS are allowed.');
        }

        $responseHeaders = [];

        $ch = curl_init();

        if (!$ch) {
            return HttpResponse::failure(
                0,
                'Unable to initialize cURL.'
            );
        }

        curl_setopt_array($ch, [

            CURLOPT_URL => $url,

            CURLOPT_CUSTOMREQUEST => strtoupper($method),

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_FOLLOWLOCATION => false,

            CURLOPT_TIMEOUT => $this->timeout,

            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),

            CURLOPT_ENCODING => '',

            CURLOPT_USERAGENT => $this->userAgent,

            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,

            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,

            CURLOPT_HEADERFUNCTION =>
                function (
                    $curl,
                    string $header
                ) use (&$responseHeaders) {

                    $length = strlen($header);

                    $header = trim($header);

                    if ($header === '') {
                        return $length;
                    }

                    if (str_contains($header, ':')) {

                        [$key, $value] =
                            explode(':', $header, 2);

                        $responseHeaders[
                            trim($key)
                        ] = trim($value);
                    }

                    return $length;
                }

        ]);

        if ($body !== null) {

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $body
            );
        }

        if ($headers) {

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array_values(array_unique($headers))
            );
        }

        $raw = curl_exec($ch);

        $errno = curl_errno($ch);

        $error = curl_error($ch);

        $status = (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        if ($errno) {

            $this->logger?->error(
                "HTTP Error ({$errno}): {$error}"
            );

            return HttpResponse::failure(
                $status,
                $error
            );
        }

        $raw = (string) $raw;

        $decoded = json_decode(
            $raw,
            true
        );

        $bodyData =
            json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : $raw;

        if ($status >= 400) {

            $this->logger?->error(
                "HTTP {$status}: {$raw}"
            );

            return HttpResponse::failure(
                $status,
                "HTTP {$status}",
                $raw,
                $responseHeaders
            );
        }

        return HttpResponse::success(
            $status,
            $bodyData,
            $raw,
            $responseHeaders
        );
    }
}
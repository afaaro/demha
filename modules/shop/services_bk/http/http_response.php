<?php

namespace App\Modules\Shop\Services\Http;

/**
 * Represents an HTTP response.
 */
class HttpResponse
{
    /**
     * Whether the request succeeded.
     */
    public bool $success = false;

    /**
     * HTTP status code.
     */
    public int $status = 0;

    /**
     * Response headers.
     *
     * @var array<string,string>
     */
    public array $headers = [];

    /**
     * Parsed response body.
     *
     * May be:
     * - array (JSON)
     * - string (plain text/XML)
     * - null
     *
     * @var array|string|null
     */
    public array|string|null $body = null;

    /**
     * Raw response body.
     */
    public string $rawBody = '';

    /**
     * cURL error (if any).
     */
    public ?string $error = null;

    /**
     * Create a successful response.
     */
    public static function success(
        int $status,
        array|string|null $body,
        string $rawBody = '',
        array $headers = []
    ): self {

        $response = new self();

        $response->success = true;
        $response->status = $status;
        $response->body = $body;
        $response->rawBody = $rawBody;
        $response->headers = $headers;

        return $response;
    }

    /**
     * Create a failed response.
     */
    public static function failure(
        int $status,
        string $error,
        string $rawBody = '',
        array $headers = []
    ): self {

        $response = new self();

        $response->success = false;
        $response->status = $status;
        $response->error = $error;
        $response->rawBody = $rawBody;
        $response->headers = $headers;

        return $response;
    }

    /**
     * True when body contains an array.
     */
    public function isJson(): bool
    {
        return is_array($this->body);
    }

    /**
     * Get a value from the JSON body.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!is_array($this->body)) {
            return $default;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * Return body as array.
     */
    public function toArray(): array
    {
        return is_array($this->body)
            ? $this->body
            : [];
    }

    /**
     * Return body as string.
     */
    public function toString(): string
    {
        if (is_string($this->body)) {
            return $this->body;
        }

        if (is_array($this->body)) {
            return json_encode(
                $this->body,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        }

        return '';
    }

    /**
     * Did the request fail?
     */
    public function failed(): bool
    {
        return !$this->success;
    }

    /**
     * Was the request successful?
     */
    public function ok(): bool
    {
        return $this->success;
    }

    /**
     * Convert to array.
     */
    public function toResponseArray(): array
    {
        return [
            'success' => $this->success,
            'status'  => $this->status,
            'headers' => $this->headers,
            'body'    => $this->body,
            'error'   => $this->error,
        ];
    }
}
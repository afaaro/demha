<?php

namespace App\Modules\Shop\Services\Http;

/**
 * Reusable OAuth2 helper.
 *
 * Used by:
 * - eBay
 * - Amazon
 * - Etsy
 * - TikTok
 * - Walmart
 * - Any OAuth2 provider
 */
trait OauthTrait
{
    /**
     * Do we currently have a valid access token?
     */
    protected function hasValidAccessToken(): bool
    {
        return !empty($this->config['access_token'])
            && time() < ($this->config['token_expires'] ?? 0);
    }

    /**
     * Get the current access token.
     */
    protected function accessToken(): string
    {
        return $this->config['access_token'] ?? '';
    }

    /**
     * Get refresh token.
     */
    protected function refreshTokenValue(): string
    {
        return $this->config['refresh_token'] ?? '';
    }

    /**
     * Has a refresh token?
     */
    protected function hasRefreshToken(): bool
    {
        return !empty($this->config['refresh_token']);
    }

    /**
     * Store OAuth tokens.
     */
    protected function storeTokens(array $response): void
    {
        if (!empty($response['access_token'])) {
            $this->config['access_token'] = $response['access_token'];
        }
        if (!empty($response['refresh_token'])) {
            $this->config['refresh_token'] = $response['refresh_token'];
        }
        $expires = (int)($response['expires_in'] ?? 7200);
        // Refresh one minute early
        $this->config['token_expires'] = time() + max(60, $expires - 60);
        $this->updateSettings();
    }

    /**
     * Remove stored tokens.
     */
    protected function clearTokens(): void
    {
        unset(
            $this->config['access_token'],
            $this->config['refresh_token'],
            $this->config['token_expires']
        );
        $this->updateSettings();
    }

    /**
     * Return Authorization header.
     */
    protected function bearerHeader(): array
    {
        return [
            'Authorization: Bearer ' . $this->accessToken()
        ];
    }

    /**
     * Merge Authorization header with additional headers.
     */
    protected function bearerHeaders(array $headers = []): array
    {
        return array_merge($this->bearerHeader(), $headers);
    }

    /**
     * Basic Authentication header.
     */
    protected function basicHeaders(): array
    {
        return [
            'Authorization: Basic ' . base64_encode(
                ($this->config['client_id'] ?? '')
                . ':'
                . ($this->config['client_secret'] ?? '')
            ),
            'Accept: application/json'
        ];
    }

    /**
     * Returns true if token expires within X seconds.
     */
    protected function tokenExpiresSoon(int $seconds = 300): bool
    {
        return ($this->config['token_expires'] ?? 0) <= (time() + $seconds);
    }

    /**
     * Ensure authentication – calls refresh if needed.
     */
    protected function ensureAuthenticated(): bool
    {
        if ($this->hasValidAccessToken()) {
            return true;
        }
        if ($this->hasRefreshToken()) {
            return $this->refreshToken();
        }
        return false;
    }

    /**
     * Build OAuth authorization URL.
     */
    protected function buildAuthorizationUrl(string $url, array $parameters): string
    {
        return $url . '?' . http_build_query($parameters);
    }

    /**
     * Standard OAuth state value.
     */
    protected function oauthState(): string
    {
        return hash('sha256', $this->channelId . microtime(true) . random_bytes(16));
    }

    /**
     * Log OAuth response.
     */
    protected function logOAuthResponse(string $action, mixed $response): void
    {
        $this->log($action . PHP_EOL . print_r($response, true));
    }

    /**
     * Child adapters must implement this method.
     */
    abstract protected function refreshToken(): bool;
}
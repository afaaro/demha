<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Services\Http\HttpClient;
use App\Modules\Shop\Services\Http\HttpResponse;
use App\System\Library\Logger;
use App\System\Library\Database;
use App\System\Engine\Registry;
use App\System\Library\Session;

/**
 * Handles OAuth 2.0 flows using the HttpClient.
 */
class OAuth_Service
{
    protected Database $db;
    protected Logger $logger;
    protected HttpClient $http;
    protected ?Session $session = null;

    public function __construct(Registry $registry)
    {
        $this->db     = $registry->get('db');
        $this->logger = $registry->get('logger');
        $this->http   = new HttpClient($this->logger);
        if ($registry->has('session')) {
            $this->session = $registry->get('session');
        }
    }

    /**
     * Returns the authorization URL for a given channel.
     */
    public function getAuthorizationUrl(int $channelId, string $redirectUri): ?string
    {
        $channel = $this->getChannel($channelId);
        if (!$channel) {
            return null;
        }

        $settings = json_decode($channel['settings'] ?? '{}', true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $clientId = $settings['client_id'] ?? null;
        $authUrl  = $settings['auth_url'] ?? null;

        if (!$clientId || !$authUrl) {
            $this->logger->error("Missing OAuth config for channel $channelId");
            return null;
        }

        $state = bin2hex(random_bytes(16));
        if ($this->session) {
            $this->session->set("oauth.state.{$channelId}", $state);
        }

        return $authUrl . '?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $settings['scope'] ?? '',
            'state'         => $state,
        ]);
    }

    /**
     * Handles the OAuth callback: exchanges code for access token.
     */
    public function handleCallback(int $channelId, string $code, string $redirectUri, ?string $state = null): bool
    {
        try {
            if ($state !== null && $this->session) {
                $expectedState = (string) $this->session->get("oauth.state.{$channelId}", '');
                $this->session->delete("oauth.state.{$channelId}");

                if ($expectedState === '' || !hash_equals($expectedState, $state)) {
                    throw new \RuntimeException('Invalid OAuth state.');
                }
            }

            $channel = $this->getChannel($channelId);
            if (!$channel) {
                throw new \RuntimeException('Channel not found.');
            }

            $settings = json_decode($channel['settings'] ?? '{}', true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $clientId     = $settings['client_id'] ?? null;
            $clientSecret = $settings['client_secret'] ?? null;
            $tokenUrl     = $settings['token_url'] ?? null;

            if (!$clientId || !$clientSecret || !$tokenUrl) {
                throw new \RuntimeException('Incomplete OAuth config.');
            }

            // Use HttpClient to POST form data
            $response = $this->http->postForm($tokenUrl, [
                'grant_type'    => 'authorization_code',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Token exchange failed: ' . $response->error);
            }

            $data = $response->toArray();
            if (empty($data['access_token'])) {
                throw new \RuntimeException('No access token returned.');
            }

            // Store tokens in channel settings
            $settings['access_token']   = $data['access_token'];
            $settings['refresh_token']  = $data['refresh_token'] ?? null;
            $settings['expires_in']     = $data['expires_in'] ?? 3600;
            $settings['token_obtained'] = time();

            $this->db->query(
                "UPDATE #__shop_channel SET settings = ? WHERE id = ?",
                [json_encode($settings, JSON_UNESCAPED_SLASHES), $channelId]
            );

            $this->logger->info("OAuth callback successful for channel $channelId");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("handleCallback failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a valid access token, refreshing if necessary.
     */
    public function getAccessToken(int $channelId): ?string
    {
        $channel = $this->getChannel($channelId);
        if (!$channel) {
            return null;
        }

        $settings = json_decode($channel['settings'] ?? '{}', true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $token = $settings['access_token'] ?? null;
        $expires = $settings['expires_in'] ?? 0;
        $obtained = $settings['token_obtained'] ?? 0;

        // Check if token is still valid (5 min buffer)
        if ($token && (time() - $obtained) < ($expires - 300)) {
            return $token;
        }

        // Token expired or missing – try to refresh
        if (!empty($settings['refresh_token'])) {
            return $this->refreshToken($channelId);
        }

        return null;
    }

    /**
     * Refreshes the access token using the refresh token.
     */
    public function refreshToken(int $channelId): ?string
    {
        try {
            $channel = $this->getChannel($channelId);
            if (!$channel) {
                return null;
            }

            $settings = json_decode($channel['settings'] ?? '{}', true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $clientId     = $settings['client_id'] ?? null;
            $clientSecret = $settings['client_secret'] ?? null;
            $tokenUrl     = $settings['token_url'] ?? null;
            $refreshToken = $settings['refresh_token'] ?? null;

            if (!$refreshToken || !$tokenUrl) {
                throw new \RuntimeException('Missing refresh token or token URL.');
            }

            $response = $this->http->postForm($tokenUrl, [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Refresh failed: ' . $response->error);
            }

            $data = $response->toArray();
            if (empty($data['access_token'])) {
                throw new \RuntimeException('No access token in refresh response.');
            }

            // Update stored tokens
            $settings['access_token']   = $data['access_token'];
            $settings['refresh_token']  = $data['refresh_token'] ?? $refreshToken; // keep old if not provided
            $settings['expires_in']     = $data['expires_in'] ?? 3600;
            $settings['token_obtained'] = time();

            $this->db->query(
                "UPDATE #__shop_channel SET settings = ? WHERE id = ?",
                [json_encode($settings, JSON_UNESCAPED_SLASHES), $channelId]
            );

            $this->logger->info("Access token refreshed for channel $channelId");
            return $settings['access_token'];
        } catch (\Exception $e) {
            $this->logger->error("refreshToken failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper to get channel record.
     */
    protected function getChannel(int $channelId): ?array
    {
        return $this->db->query(
            "SELECT * FROM #__shop_channel WHERE id = ?",
            [$channelId]
        )->row ?: null;
    }
}
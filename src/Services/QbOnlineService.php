<?php

namespace App\Services;

class QbOnlineService
{
    private \PDO $db;
    private string $clientId;
    private string $clientSecret;
    private string $realmId;
    private string $accessToken;
    private string $refreshToken;

    const AUTH_URL = 'https://appcenter.intuit.com/connect/oauth2';
    const TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
    const API_BASE = 'https://quickbooks.api.intuit.com/v3/company/';

    public function __construct(\PDO $db)
    {
        $this->db = $db;
        $this->clientId = $this->getSetting('qb_online_client_id');
        $this->clientSecret = $this->getSetting('qb_online_client_secret');
        $this->realmId = $this->getSetting('qb_online_realm_id');
        $this->accessToken = $this->getSetting('qb_online_access_token');
        $this->refreshToken = $this->getSetting('qb_online_refresh_token');
    }

    public function getAuthUrl(string $redirectUri): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['qb_oauth_state'] = $state;

        $params = http_build_query([
            'client_id' => $this->clientId,
            'scope' => 'com.intuit.quickbooks.accounting',
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ]);
        return self::AUTH_URL . '?' . $params;
    }

    public function exchangeCode(string $code, string $redirectUri, string $realmId): bool
    {
        $response = $this->httpPost(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ], ['Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)]);

        if (!$response || !isset($response['access_token'])) return false;

        $this->saveSetting('qb_online_access_token', $response['access_token']);
        $this->saveSetting('qb_online_refresh_token', $response['refresh_token'] ?? '');
        $this->saveSetting('qb_online_realm_id', $realmId);
        $this->saveSetting('qb_online_token_expires_at', date('Y-m-d H:i:s', time() + ($response['expires_in'] ?? 3600)));
        $this->accessToken = $response['access_token'];
        $this->realmId = $realmId;
        return true;
    }

    public function refreshAccessToken(): bool
    {
        if (empty($this->refreshToken)) return false;

        $response = $this->httpPost(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ], ['Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)]);

        if (!$response || !isset($response['access_token'])) return false;

        $this->saveSetting('qb_online_access_token', $response['access_token']);
        if (!empty($response['refresh_token'])) {
            $this->saveSetting('qb_online_refresh_token', $response['refresh_token']);
        }
        $this->saveSetting('qb_online_token_expires_at', date('Y-m-d H:i:s', time() + ($response['expires_in'] ?? 3600)));
        $this->accessToken = $response['access_token'];
        return true;
    }

    public function isConnected(): bool
    {
        if (empty($this->accessToken) || empty($this->realmId)) return false;
        $expires = $this->getSetting('qb_online_token_expires_at');
        if ($expires && strtotime($expires) < time()) {
            return $this->refreshAccessToken();
        }
        return true;
    }

    public function getCompanyInfo(): ?array
    {
        if (!$this->isConnected()) return null;
        $result = $this->httpGet(self::API_BASE . $this->realmId . '/companyinfo/' . $this->realmId . '?minorversion=65');
        return $result['CompanyInfo'] ?? null;
    }

    public function disconnect(): void
    {
        $this->saveSetting('qb_online_access_token', '');
        $this->saveSetting('qb_online_refresh_token', '');
        $this->saveSetting('qb_online_realm_id', '');
        $this->saveSetting('qb_online_token_expires_at', '');
    }

    // TODO Phase 2: createInvoice(), createBill(), createCustomer(), createVendor(), createItem()

    private function httpGet(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response ? json_decode($response, true) : null;
    }

    private function httpPost(string $url, array $data, array $headers = []): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response ? json_decode($response, true) : null;
    }

    private function getSetting(string $key): string
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM qb_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    private function saveSetting(string $key, string $value): void
    {
        $this->db->prepare('INSERT INTO qb_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()')
            ->execute([$key, $value]);
    }
}

<?php

require_once __DIR__ . '/../dns/provider.php';
require_once __DIR__ . '/../../resource/js/auth.php';

function oauth_config(): array
{
    $config = auth_config();
    return $config['oauth'] ?? [];
}

function oauth_base_url(): string
{
    $config = auth_config();
    $baseUrl = trim((string) ($config['app']['base_url'] ?? ''));
    if ($baseUrl !== '') {
        return rtrim($baseUrl, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function oauth_provider_map(): array
{
    return [
        'github' => 'GitHub',
        'google' => 'Google',
        'nodeloc' => 'NodeLoc',
    ];
}

function oauth_provider_config(string $provider): array
{
    $config = oauth_config();
    return $config[$provider] ?? [];
}

function oauth_provider_enabled(string $provider): bool
{
    $config = oauth_provider_config($provider);
    return !empty($config['enabled']) && !empty($config['client_id']) && !empty($config['client_secret']);
}

function oauth_callback_url(string $provider): string
{
    return oauth_base_url() . '/module/oauth/callback.php?provider=' . urlencode($provider);
}

function oauth_state_key(string $provider): string
{
    return 'oauth_state_' . $provider;
}

function oauth_return_key(string $provider): string
{
    return 'oauth_return_' . $provider;
}

function oauth_boot_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function oauth_generate_state(string $provider): string
{
    oauth_boot_session();
    $state = bin2hex(random_bytes(16));
    $_SESSION[oauth_state_key($provider)] = $state;
    return $state;
}

function oauth_validate_state(string $provider, string $state): bool
{
    oauth_boot_session();
    $stored = (string) ($_SESSION[oauth_state_key($provider)] ?? '');
    unset($_SESSION[oauth_state_key($provider)]);

    return $stored !== '' && hash_equals($stored, $state);
}

function oauth_http_request(string $method, string $url, array $payload = [], array $headers = []): array
{
    $method = strtoupper($method);
    $body = '';
    $hasContentType = false;

    foreach ($headers as $header) {
        if (stripos($header, 'content-type:') === 0) {
            $hasContentType = true;
            break;
        }
    }

    if ($method === 'GET' && !empty($payload)) {
        $query = http_build_query($payload);
        $url .= (str_contains($url, '?') ? '&' : '?') . $query;
    } elseif (!empty($payload)) {
        $body = http_build_query($payload);
        if (!$hasContentType) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'status' => 0,
            'headers' => [],
            'body' => null,
            'error' => $error ?: 'request failed',
        ];
    }

    return [
        'success' => $status >= 200 && $status < 300,
        'status' => $status,
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
        'error' => null,
    ];
}

function oauth_authorize_url(string $provider, string $state): string
{
    $config = oauth_provider_config($provider);
    $redirectUri = ($config['redirect_uri'] ?? '') !== '' ? $config['redirect_uri'] : oauth_callback_url($provider);
    $scope = $config['scope'] ?? '';

    if ($provider === 'github') {
        $query = http_build_query([
            'client_id' => $config['client_id'] ?? '',
            'redirect_uri' => $redirectUri,
            'scope' => $scope ?: 'read:user user:email',
            'state' => $state,
        ]);

        return rtrim((string) ($config['authorize_url'] ?? 'https://github.com/login/oauth/authorize'), '/') . '?' . $query;
    }

    if ($provider === 'google') {
        $query = http_build_query([
            'client_id' => $config['client_id'] ?? '',
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope ?: 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return rtrim((string) ($config['authorize_url'] ?? 'https://accounts.google.com/o/oauth2/v2/auth'), '/') . '?' . $query;
    }

    if ($provider === 'nodeloc') {
        $query = http_build_query([
            'client_id' => $config['client_id'] ?? '',
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope ?: 'openid profile email',
            'state' => $state,
        ]);

        return rtrim((string) ($config['authorize_url'] ?? 'https://www.nodeloc.com/oauth-provider/authorize'), '/') . '?' . $query;
    }

    throw new RuntimeException('Unsupported OAuth provider.');
}

function oauth_exchange_code(string $provider, string $code): array
{
    $config = oauth_provider_config($provider);
    $redirectUri = ($config['redirect_uri'] ?? '') !== '' ? $config['redirect_uri'] : oauth_callback_url($provider);

    if ($provider === 'github') {
        $response = oauth_http_request('POST', (string) ($config['token_url'] ?? 'https://github.com/login/oauth/access_token'), [
            'client_id' => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ], [
            'Accept: application/json',
            'User-Agent: ' . ($config['user_agent'] ?? 'DomainDistributionOAuth'),
        ]);

        $body = json_decode($response['body'] ?? '', true);
        return is_array($body) ? $body : [];
    }

    if ($provider === 'google') {
        $response = oauth_http_request('POST', (string) ($config['token_url'] ?? 'https://oauth2.googleapis.com/token'), [
            'client_id' => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ], [
            'Accept: application/json',
            'User-Agent: ' . ($config['user_agent'] ?? 'DomainDistributionOAuth'),
        ]);

        $body = json_decode($response['body'] ?? '', true);
        return is_array($body) ? $body : [];
    }

    if ($provider === 'nodeloc') {
        $response = oauth_http_request('POST', (string) ($config['token_url'] ?? 'https://www.nodeloc.com/oauth-provider/token'), [
            'client_id' => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ], [
            'Accept: application/json',
            'User-Agent: ' . ($config['user_agent'] ?? 'DomainDistributionOAuth'),
        ]);

        $body = json_decode($response['body'] ?? '', true);
        return is_array($body) ? $body : [];
    }

    throw new RuntimeException('Unsupported OAuth provider.');
}

function oauth_fetch_profile(string $provider, string $accessToken): array
{
    $config = oauth_provider_config($provider);

    if ($provider === 'github') {
        $response = oauth_http_request('GET', (string) ($config['user_url'] ?? 'https://api.github.com/user'), [], [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: ' . ($config['user_agent'] ?? 'DomainDistributionOAuth'),
        ]);
        $profile = json_decode($response['body'] ?? '', true);
        $profile = is_array($profile) ? $profile : [];

        if (empty($profile['email']) && !empty($config['email_url'])) {
            $emailResponse = oauth_http_request('GET', (string) $config['email_url'], [], [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
                'User-Agent: ' . ($config['user_agent'] ?? 'DomainDistributionOAuth'),
            ]);
            $emails = json_decode($emailResponse['body'] ?? '', true);
            if (is_array($emails)) {
                foreach ($emails as $emailRow) {
                    if (!empty($emailRow['primary']) && !empty($emailRow['email'])) {
                        $profile['email'] = $emailRow['email'];
                        $profile['email_verified'] = !empty($emailRow['verified']);
                        break;
                    }
                }
            }
        }

        return [
            'provider_user_id' => (string) ($profile['id'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'name' => (string) ($profile['name'] ?? $profile['login'] ?? ''),
            'avatar' => (string) ($profile['avatar_url'] ?? ''),
            'email_verified' => !empty($profile['email_verified']),
            'raw' => $profile,
        ];
    }

    if ($provider === 'google') {
        $response = oauth_http_request('GET', (string) ($config['user_url'] ?? 'https://www.googleapis.com/oauth2/v2/userinfo'), [], [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ]);
        $profile = json_decode($response['body'] ?? '', true);
        $profile = is_array($profile) ? $profile : [];

        return [
            'provider_user_id' => (string) ($profile['id'] ?? $profile['sub'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'name' => (string) ($profile['name'] ?? ''),
            'avatar' => (string) ($profile['picture'] ?? ''),
            'email_verified' => !empty($profile['verified_email']) || !empty($profile['email_verified']),
            'raw' => $profile,
        ];
    }

    if ($provider === 'nodeloc') {
        $response = oauth_http_request('GET', (string) ($config['user_url'] ?? 'https://www.nodeloc.com/oauth-provider/userinfo'), [], [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: ' . ($config['user_agent'] ?? 'DomainDistributionOAuth'),
        ]);
        $profile = json_decode($response['body'] ?? '', true);
        $profile = is_array($profile) ? $profile : [];

        return [
            'provider_user_id' => (string) ($profile['id'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'name' => (string) ($profile['name'] ?? $profile['username'] ?? ''),
            'avatar' => (string) ($profile['avatar_url'] ?? ''),
            'email_verified' => !empty($profile['email']),
            'raw' => $profile,
        ];
    }

    throw new RuntimeException('Unsupported OAuth provider.');
}

function oauth_generate_username(string $provider, string $providerUserId): string
{
    $base = 'oauth_' . preg_replace('/[^a-z0-9_]+/i', '_', $provider) . '_' . substr(md5($providerUserId), 0, 8);
    $candidate = $base;
    $i = 1;

    while (auth_user_by_username($candidate)) {
        $candidate = $base . '_' . $i;
        $i++;
    }

    return $candidate;
}

function oauth_user_by_provider(string $provider, string $providerUserId): ?array
{
    $pdo = auth_db();
    $stmt = $pdo->prepare(
        'SELECT u.* FROM oauth_accounts oa
         INNER JOIN users u ON u.id = oa.user_id
         WHERE oa.provider = :provider AND oa.provider_user_id = :provider_user_id
         LIMIT 1'
    );
    $stmt->execute([
        ':provider' => $provider,
        ':provider_user_id' => $providerUserId,
    ]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function oauth_user_by_email(string $email): ?array
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function oauth_link_account(int $userId, string $provider, string $providerUserId, ?string $email = null, ?string $name = null, ?string $avatar = null): bool
{
    $pdo = auth_db();
    $stmt = $pdo->prepare(
        'INSERT INTO oauth_accounts (user_id, provider, provider_user_id, provider_email, provider_name, avatar)
         VALUES (:user_id, :provider, :provider_user_id, :provider_email, :provider_name, :avatar)
         ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            provider_email = VALUES(provider_email),
            provider_name = VALUES(provider_name),
            avatar = VALUES(avatar),
            updated_at = NOW()'
    );

    return $stmt->execute([
        ':user_id' => $userId,
        ':provider' => $provider,
        ':provider_user_id' => $providerUserId,
        ':provider_email' => $email,
        ':provider_name' => $name,
        ':avatar' => $avatar,
    ]);
}

function oauth_create_local_user(array $profile, string $provider): int
{
    $pdo = auth_db();
    $username = oauth_generate_username($provider, (string) $profile['provider_user_id']);
    $password = password_hash(auth_token(32), PASSWORD_DEFAULT);
    $email = trim((string) ($profile['email'] ?? ''));
    $nickname = trim((string) ($profile['name'] ?? ''));
    $verifiedAt = !empty($profile['email_verified']) ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password, nickname, email, status, email_verified_at)
         VALUES (:username, :password, :nickname, :email, 1, :email_verified_at)'
    );
    $stmt->execute([
        ':username' => $username,
        ':password' => $password,
        ':nickname' => $nickname !== '' ? $nickname : null,
        ':email' => $email !== '' ? $email : null,
        ':email_verified_at' => $verifiedAt,
    ]);

    return (int) $pdo->lastInsertId();
}

function oauth_login_user_by_id(int $userId): void
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $userId]);

    oauth_boot_session();
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['username'];
    }
}

function oauth_login_or_bind(string $provider, array $profile): int
{
    $providerUserId = trim((string) ($profile['provider_user_id'] ?? ''));
    if ($providerUserId === '') {
        throw new RuntimeException('OAuth profile missing provider user id.');
    }

    $user = oauth_user_by_provider($provider, $providerUserId);
    if ($user) {
        oauth_login_user_by_id((int) $user['id']);
        return (int) $user['id'];
    }

    $email = trim((string) ($profile['email'] ?? ''));
    if ($email !== '') {
        $local = strstr($email, '@', true);
        if ($local !== false && (str_contains($local, '+') || str_contains($local, '.'))) {
            throw new RuntimeException('不支持带 + 或 . 的别名邮箱登录。');
        }
        $allowed = ['gmail.com', 'qq.com', '163.com', 'outlook.com'];
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        if (!in_array($domain, $allowed, true)) {
            throw new RuntimeException('仅支持 gmail.com、qq.com、163.com、outlook.com 邮箱登录。');
        }
        $user = oauth_user_by_email($email);
        if ($user) {
            oauth_link_account((int) $user['id'], $provider, $providerUserId, $email, (string) ($profile['name'] ?? ''), (string) ($profile['avatar'] ?? ''));
            oauth_login_user_by_id((int) $user['id']);
            return (int) $user['id'];
        }
    }

    $userId = oauth_create_local_user($profile, $provider);
    oauth_link_account($userId, $provider, $providerUserId, $email !== '' ? $email : null, (string) ($profile['name'] ?? ''), (string) ($profile['avatar'] ?? ''));
    oauth_login_user_by_id($userId);

    return $userId;
}

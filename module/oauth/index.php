<?php
if (!file_exists(__DIR__ . '/../../install/install.lock')) {
    header('Location: /install/install.php');
    exit;
}
require_once __DIR__ . '/service.php';

function oauth_provider_login_url(string $provider): string
{
    if (!oauth_provider_enabled($provider)) {
        throw new RuntimeException('OAuth provider not enabled.');
    }

    oauth_boot_session();
    $state = oauth_generate_state($provider);
    $_SESSION[oauth_return_key($provider)] = $_GET['return'] ?? '/user/dashboard/';

    return oauth_authorize_url($provider, $state);
}

function oauth_process_callback(string $provider, string $code, string $state): int
{
    if (!oauth_provider_enabled($provider)) {
        throw new RuntimeException('OAuth provider not enabled.');
    }

    if (!oauth_validate_state($provider, $state)) {
        throw new RuntimeException('Invalid OAuth state.');
    }

    $tokenData = oauth_exchange_code($provider, $code);
    $accessToken = (string) ($tokenData['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('OAuth access token missing.');
    }

    $profile = oauth_fetch_profile($provider, $accessToken);
    return oauth_login_or_bind($provider, $profile);
}

<?php
if (!file_exists(__DIR__ . '/../../install/install.lock')) {
    header('Location: /install/install.php');
    exit;
}
require_once __DIR__ . '/index.php';

$provider = trim((string) ($_GET['provider'] ?? ''));

if ($provider === '') {
    die('Missing provider.');
}

$url = oauth_provider_login_url($provider);
header('Location: ' . $url);
exit;

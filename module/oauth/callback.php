<?php
if (!file_exists(__DIR__ . '/../../install/install.lock')) {
    header('Location: /install/install.php');
    exit;
}
require_once __DIR__ . '/index.php';

$provider = trim((string) ($_GET['provider'] ?? ''));
$code = trim((string) ($_GET['code'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));

if ($provider === '' || $code === '' || $state === '') {
    die('Invalid OAuth callback.');
}

$userId = oauth_process_callback($provider, $code, $state);
$return = '/user/dashboard/';
oauth_boot_session();
if (!empty($_SESSION[oauth_return_key($provider)])) {
    $return = (string) $_SESSION[oauth_return_key($provider)];
    unset($_SESSION[oauth_return_key($provider)]);
}

header('Location: ' . $return);
exit;

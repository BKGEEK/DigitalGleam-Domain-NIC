<?php

require_once __DIR__ . '/../../lang/helper.php';
require_once __DIR__ . '/../../module/whois/api.php';

$query = trim((string) ($_GET['query'] ?? ''));

if ($query === '') {
    whois_api_json(false, __('whois.api.missing_query'), [], 400);
}

$result = whois_api_lookup($query);

if (!$result['success']) {
    whois_api_json(false, $result['message'], [], 404);
}

whois_api_json(true, 'ok', $result['data']);
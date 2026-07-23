<?php
require __DIR__ . '/../layout.php';

$pdo = auth_db();
$userId = (int) $_SESSION['user_id'];

$recordConfig = (require __DIR__ . '/../../config/config.php')['domain'] ?? [];
$maxNsRecords = (int) ($recordConfig['max_ns_records'] ?? 5);
$maxTxtRecords = (int) ($recordConfig['max_txt_records'] ?? 3);
$maxARecords = (int) ($recordConfig['max_a_records'] ?? 10);
$maxAaaaRecords = (int) ($recordConfig['max_aaaa_records'] ?? 10);
$maxCnameRecords = (int) ($recordConfig['max_cname_records'] ?? 10);
$enableNsRecords = !empty($recordConfig['enable_ns_records']);
$enableTxtRecords = !empty($recordConfig['enable_txt_records']);
$enableARecords = !empty($recordConfig['enable_a_records']);
$enableAaaaRecords = !empty($recordConfig['enable_aaaa_records']);
$enableCnameRecords = !empty($recordConfig['enable_cname_records']);
$registrationMonths = (int) ($recordConfig['registration_months'] ?? 12);
$renewalGraceMonths = (int) ($recordConfig['renewal_grace_months'] ?? 3);
$renewalMonths = (int) ($recordConfig['renewal_months'] ?? 12);

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? 'success';

$stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.assigned_to = :user_id ORDER BY d.id DESC');
$stmt->execute([':user_id' => $userId]);
$rows = $stmt->fetchAll();

$manageDomainId = isset($_GET['manage_ns']) ? (int) $_GET['manage_ns'] : (isset($_GET['manage_txt']) ? (int) $_GET['manage_txt'] : (isset($_GET['manage_dns']) ? (int) $_GET['manage_dns'] : 0));
$manageType = isset($_GET['manage_ns']) ? 'ns' : (isset($_GET['manage_txt']) ? 'txt' : (isset($_GET['manage_dns']) ? 'dns' : ''));
$manageDomain = null;
$nsRecords = [];
$txtRecords = [];
$dnsRecords = [];
if ($manageDomainId > 0) {
    foreach ($rows as $row) {
        if ((int) $row['id'] === $manageDomainId) {
            $manageDomain = $row;
            break;
        }
    }
    if ($manageDomain) {
        if ($manageType === 'ns') {
            $nsRecords = dns_ns_records_by_domain_id($manageDomainId);
        } elseif ($manageType === 'txt') {
            $txtRecords = dns_txt_records_by_domain_id($manageDomainId);
        } else {
            $dnsRecords = dns_records_by_domain_id($manageDomainId);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $redirect = '/user/domains/';
    $action = $_POST['action'];
    $domainId = (int) ($_POST['domain_id'] ?? 0);

    $ownsDomain = false;
    foreach ($rows as $row) {
        if ((int) $row['id'] === $domainId) {
            $ownsDomain = true;
            break;
        }
    }

    if (!$ownsDomain) {
        $redirect .= '?message=' . urlencode(__('user.domains.error_not_owned')) . '&type=error';
    } elseif ($action === 'add_ns' && $domainId > 0) {
        $nameserver = trim($_POST['nameserver'] ?? '');
        $result = dns_ns_record_add($domainId, $nameserver);
        $redirect .= '?manage_ns=' . $domainId . '&message=' . urlencode($result['message']) . '&type=' . ($result['success'] ? 'success' : 'error');
    } elseif ($action === 'delete_ns' && $domainId > 0) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        if ($recordId > 0) {
            $result = dns_ns_record_delete($recordId);
            $redirect .= '?manage_ns=' . $domainId . '&message=' . urlencode($result['message']) . '&type=' . ($result['success'] ? 'success' : 'error');
        }
    } elseif ($action === 'add_txt' && $domainId > 0) {
        $value = trim($_POST['value'] ?? '');
        $result = dns_txt_record_add($domainId, $value);
        $redirect .= '?manage_txt=' . $domainId . '&message=' . urlencode($result['message']) . '&type=' . ($result['success'] ? 'success' : 'error');
    } elseif ($action === 'delete_txt' && $domainId > 0) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        if ($recordId > 0) {
            $result = dns_txt_record_delete($recordId);
            $redirect .= '?manage_txt=' . $domainId . '&message=' . urlencode($result['message']) . '&type=' . ($result['success'] ? 'success' : 'error');
        }
    } elseif ($action === 'add_dns' && $domainId > 0) {
        $type = trim($_POST['dns_type'] ?? 'A');
        $name = trim($_POST['dns_name'] ?? '@');
        $value = trim($_POST['dns_value'] ?? '');
        $proxied = !empty($_POST['dns_proxied']);
        $result = dns_record_add($domainId, $type, $name, $value, $proxied);
        $redirect .= '?manage_dns=' . $domainId . '&message=' . urlencode($result['message']) . '&type=' . ($result['success'] ? 'success' : 'error');
    } elseif ($action === 'delete_dns' && $domainId > 0) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        if ($recordId > 0) {
            $result = dns_record_delete($recordId);
            $redirect .= '?manage_dns=' . $domainId . '&message=' . urlencode($result['message']) . '&type=' . ($result['success'] ? 'success' : 'error');
        }
    } elseif ($action === 'renew' && $domainId > 0) {
        if ($renewalMonths <= 0) {
            $redirect .= '?message=' . urlencode(__('user.domains.error_renew_disabled')) . '&type=error';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM domains WHERE id = :id AND assigned_to = :user_id LIMIT 1');
            $stmt->execute([':id' => $domainId, ':user_id' => $userId]);
            $domain = $stmt->fetch();
            if (!$domain) {
                $redirect .= '?message=' . urlencode(__('user.domains.error_not_found')) . '&type=error';
            } else {
                $expiresAt = $domain['expires_at'] ?? null;
                $now = time();
                $canRenew = false;
                if ($expiresAt === null) {
                    $canRenew = false;
                    $redirect .= '?message=' . urlencode(__('user.domains.error_permanent')) . '&type=error';
                } else {
                    $expTs = strtotime($expiresAt);
                    $graceStart = strtotime("-{$renewalGraceMonths} months", $expTs);
                    $canRenew = $now >= $graceStart;
                    if (!$canRenew) {
                        $redirect .= '?message=' . urlencode(__('user.domains.error_not_yet')) . '&type=error';
                    }
                }
                if ($canRenew) {
                    $newExpiresAt = date('Y-m-d H:i:s', strtotime("+{$renewalMonths} months", $expTs));
                    $stmt = $pdo->prepare('UPDATE domains SET expires_at = :expires_at, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([':id' => $domainId, ':expires_at' => $newExpiresAt]);
                    $redirect .= '?message=' . urlencode(__('user.domains.renew_success', ['date' => $newExpiresAt])) . '&type=success';
                }
            }
        }
    }

    header('Location: ' . $redirect);
    exit;
}

$title = $manageDomain ? ($manageType === 'ns' ? __('user.domains.ns_management') : ($manageType === 'txt' ? __('user.domains.txt_management') : __('user.domains.dns_management'))) . ' - ' . htmlspecialchars(dns_domain_display_name($manageDomain)) : __('user.domains.title');
$activeKey = 'domains';
$isCloudflare = $manageDomain && $manageDomain['provider'] === 'cloudflare';

user_render($title, $activeKey, function () use ($rows, $manageDomain, $manageDomainId, $manageType, $nsRecords, $txtRecords, $dnsRecords, $message, $messageType, $isCloudflare, $maxNsRecords, $maxTxtRecords, $maxARecords, $maxAaaaRecords, $maxCnameRecords, $enableNsRecords, $enableTxtRecords, $enableARecords, $enableAaaaRecords, $enableCnameRecords, $pdo, $userId, $renewalGraceMonths, $renewalMonths): void {
    ?>
    <section class="panel">
        <?php if ($manageDomain && $manageType === 'ns'): ?>
            <div class="flex items-center justify-between">
                <div>
                    <a href="/user/domains/" class="text-sm text-brand-600 hover:text-brand-700">&larr; <?= __('user.domains.back') ?></a>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= __('user.domains.ns_management') ?></h1>
                    <p class="mt-1 text-sm text-slate-600">
                        <?= __('user.domains.domain_label') ?><strong><?= htmlspecialchars(dns_domain_display_name($manageDomain)) ?></strong>
                        （<?= count($nsRecords) ?>/<?= $maxNsRecords ?> <?= __('user.domains.record_count') ?>）
                    </p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= $messageType === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Nameserver</th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.sync_status') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($nsRecords)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400"><?= __('user.domains.no_ns_records') ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($nsRecords as $i => $ns): ?>
                                <tr>
                                    <td class="px-4 py-3 text-slate-400"><?= $i + 1 ?></td>
                                    <td class="px-4 py-3 font-mono text-slate-900"><?= htmlspecialchars($ns['nameserver']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($ns['provider_record_id'])): ?>
                                            <span class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700"><?= __('user.domains.synced') ?></span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full border border-yellow-200 bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700"><?= __('user.domains.local_only') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <form method="post" action="/user/domains/?manage_ns=<?= $manageDomainId ?>" onsubmit="return confirm('<?= __('user.domains.confirm_delete_ns') ?>')">
                                            <input type="hidden" name="action" value="delete_ns">
                                            <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                                            <input type="hidden" name="record_id" value="<?= (int) $ns['id'] ?>">
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800"><?= __('user.domains.delete') ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($maxNsRecords <= 0 || count($nsRecords) < $maxNsRecords): ?>
                <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-slate-900"><?= __('user.domains.add_ns') ?></h2>
                    <form method="post" action="/user/domains/?manage_ns=<?= $manageDomainId ?>" class="mt-4 flex flex-wrap items-end gap-4">
                        <input type="hidden" name="action" value="add_ns">
                        <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-700">Nameserver</label>
                            <input type="text" name="nameserver" required placeholder="<?= __('user.domains.ns_placeholder') ?>" class="mt-1 block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <button type="submit" class="btn-primary"><?= __('user.domains.add') ?></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="mt-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                    <?= __('user.domains.max_records_reached', ['max' => $maxNsRecords]) ?>
                </div>
            <?php endif; ?>

        <?php elseif ($manageDomain && $manageType === 'txt'): ?>
            <div class="flex items-center justify-between">
                <div>
                    <a href="/user/domains/" class="text-sm text-brand-600 hover:text-brand-700">&larr; <?= __('user.domains.back') ?></a>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= __('user.domains.txt_management') ?></h1>
                    <p class="mt-1 text-sm text-slate-600">
                        <?= __('user.domains.domain_label') ?><strong><?= htmlspecialchars(dns_domain_display_name($manageDomain)) ?></strong>
                        （<?= count($txtRecords) ?>/<?= $maxTxtRecords ?> <?= __('user.domains.record_count') ?>）
                    </p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= $messageType === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.value') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.sync_status') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($txtRecords)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400"><?= __('user.domains.no_txt_records') ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($txtRecords as $i => $tr): ?>
                                <tr>
                                    <td class="px-4 py-3 text-slate-400"><?= $i + 1 ?></td>
                                    <td class="px-4 py-3 font-mono text-slate-900 break-all"><?= htmlspecialchars($tr['value']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($tr['provider_record_id'])): ?>
                                            <span class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700"><?= __('user.domains.synced') ?></span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full border border-yellow-200 bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700"><?= __('user.domains.local_only') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <form method="post" action="/user/domains/?manage_txt=<?= $manageDomainId ?>" onsubmit="return confirm('<?= __('user.domains.confirm_delete_txt') ?>')">
                                            <input type="hidden" name="action" value="delete_txt">
                                            <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                                            <input type="hidden" name="record_id" value="<?= (int) $tr['id'] ?>">
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800"><?= __('user.domains.delete') ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($maxTxtRecords <= 0 || count($txtRecords) < $maxTxtRecords): ?>
                <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-slate-900"><?= __('user.domains.add_txt') ?></h2>
                    <form method="post" action="/user/domains/?manage_txt=<?= $manageDomainId ?>" class="mt-4 flex flex-wrap items-end gap-4">
                        <input type="hidden" name="action" value="add_txt">
                        <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-700"><?= __('user.domains.value') ?></label>
                            <input type="text" name="value" required placeholder="<?= __('user.domains.txt_placeholder') ?>" class="mt-1 block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <button type="submit" class="btn-primary"><?= __('user.domains.add') ?></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="mt-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                    <?= __('user.domains.max_records_reached', ['max' => $maxTxtRecords]) ?>
                </div>
            <?php endif; ?>

        <?php elseif ($manageDomain && $manageType === 'dns'): ?>
            <div class="flex items-center justify-between">
                <div>
                    <a href="/user/domains/" class="text-sm text-brand-600 hover:text-brand-700">&larr; <?= __('user.domains.back') ?></a>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= __('user.domains.dns_management') ?></h1>
                    <p class="mt-1 text-sm text-slate-600">
                        <?= __('user.domains.domain_label') ?><strong><?= htmlspecialchars(dns_domain_display_name($manageDomain)) ?></strong>
                        <?php if ($isCloudflare): ?>
                            <span class="ml-2 inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700"><?= __('user.domains.cloudflare_available') ?></span>
                        <?php endif; ?>
                    </p>
                    <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">
                        <?php
                        $counts = ['A' => 0, 'AAAA' => 0, 'CNAME' => 0];
                        foreach ($dnsRecords as $dr) {
                            $t = strtoupper($dr['type']);
                            if (isset($counts[$t])) $counts[$t]++;
                        }
                        $typeLabels = [
                            'A' => ['enabled' => $enableARecords, 'max' => $maxARecords],
                            'AAAA' => ['enabled' => $enableAaaaRecords, 'max' => $maxAaaaRecords],
                            'CNAME' => ['enabled' => $enableCnameRecords, 'max' => $maxCnameRecords],
                        ];
                        foreach ($typeLabels as $t => $cfg): ?>
                            <?php if ($cfg['enabled']): ?>
                                <span><?= $t ?>: <?= $counts[$t] ?>/<?= $cfg['max'] <= 0 ? '∞' : $cfg['max'] ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= $messageType === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.type') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.name') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.value_short') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.cdn') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.sync_status') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('user.domains.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($dnsRecords)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400"><?= __('user.domains.no_dns_records') ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dnsRecords as $i => $dr): ?>
                                <tr>
                                    <td class="px-4 py-3 text-slate-400"><?= $i + 1 ?></td>
                                    <td class="px-4 py-3 font-mono text-slate-900"><?= htmlspecialchars($dr['type']) ?></td>
                                    <td class="px-4 py-3 font-mono text-slate-900"><?= htmlspecialchars($dr['name']) ?></td>
                                    <td class="px-4 py-3 font-mono text-slate-900 break-all"><?= htmlspecialchars($dr['value']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($isCloudflare): ?>
                                            <?php if (!empty($dr['proxied'])): ?>
                                                <span class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-2.5 py-0.5 text-xs font-medium text-orange-700"><?= __('user.domains.enabled') ?></span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-500"><?= __('user.domains.disabled') ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($dr['provider_record_id'])): ?>
                                            <span class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700"><?= __('user.domains.synced') ?></span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full border border-yellow-200 bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700"><?= __('user.domains.local_only') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <form method="post" action="/user/domains/?manage_dns=<?= $manageDomainId ?>" onsubmit="return confirm('<?= __('user.domains.confirm_delete_dns') ?>')">
                                            <input type="hidden" name="action" value="delete_dns">
                                            <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                                            <input type="hidden" name="record_id" value="<?= (int) $dr['id'] ?>">
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800"><?= __('user.domains.delete') ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $typeLimits = ['A' => $maxARecords, 'AAAA' => $maxAaaaRecords, 'CNAME' => $maxCnameRecords];
            $typeEnabled = ['A' => $enableARecords, 'AAAA' => $enableAaaaRecords, 'CNAME' => $enableCnameRecords];
            $typeCounts = ['A' => 0, 'AAAA' => 0, 'CNAME' => 0];
            foreach ($dnsRecords as $dr) {
                $t = strtoupper($dr['type']);
                if (isset($typeCounts[$t])) $typeCounts[$t]++;
            }
            $canAdd = false;
            foreach ($typeLimits as $t => $limit) {
                if ($typeEnabled[$t] && ($limit <= 0 || $typeCounts[$t] < $limit)) { $canAdd = true; break; }
            }
            ?>
            <?php if ($canAdd): ?>
                <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-slate-900"><?= __('user.domains.add_dns') ?></h2>
                    <form method="post" action="/user/domains/?manage_dns=<?= $manageDomainId ?>" class="mt-4 flex flex-wrap items-end gap-4">
                        <input type="hidden" name="action" value="add_dns">
                        <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-700"><?= __('user.domains.type') ?></label>
                            <select name="dns_type" class="mt-1 block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                <?php if ($enableARecords): ?><option value="A">A</option><?php endif; ?>
                                <?php if ($enableAaaaRecords): ?><option value="AAAA">AAAA</option><?php endif; ?>
                                <?php if ($enableCnameRecords): ?><option value="CNAME">CNAME</option><?php endif; ?>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-700"><?= __('user.domains.name') ?></label>
                            <input type="text" name="dns_name" value="@" placeholder="<?= __('user.domains.name_placeholder') ?>" class="mt-1 block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <div class="flex-[2]">
                            <label class="block text-sm font-medium text-slate-700"><?= __('user.domains.value_short') ?></label>
                            <input type="text" name="dns_value" required placeholder="<?= __('user.domains.value_placeholder') ?>" class="mt-1 block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <?php if ($isCloudflare): ?>
                            <div class="flex items-center gap-2 pb-1">
                                <input type="checkbox" name="dns_proxied" value="1" id="dns_proxied" class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                                <label for="dns_proxied" class="text-sm text-slate-700"><?= __('user.domains.enable_cdn') ?></label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn-primary"><?= __('user.domains.add') ?></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="mt-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                    <?= __('user.domains.all_limits_reached') ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <h1 class="text-2xl font-semibold text-slate-900"><?= __('user.domains.title') ?></h1>
            <p class="mt-3 text-sm text-slate-600"><?= __('user.domains.desc') ?></p>

            <?php if ($message): ?>
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= $messageType === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white">
<table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium"><?= __('user.domains.full_domain') ?></th>
                                <th class="px-4 py-3 font-medium"><?= __('user.domains.status') ?></th>
                                <th class="px-4 py-3 font-medium"><?= __('user.domains.expiry') ?></th>
                                <th class="px-4 py-3 font-medium"><?= __('user.domains.remark') ?></th>
                                <th class="px-4 py-3 font-medium"><?= __('user.domains.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $expiresAt = $row['expires_at'] ?? null;
                                $now = time();
                                $expStatus = '';
                                $expLabel = '';
                                if ($expiresAt === null) {
                                    $expStatus = 'permanent';
                                    $expLabel = __('user.domains.permanent');
                                } else {
                                    $expTs = strtotime($expiresAt);
                                    if ($expTs < $now) {
                                        $expStatus = 'expired';
                                        $expLabel = __('user.domains.expired');
                                    } else {
                                        $graceStart = strtotime("-{$renewalGraceMonths} months", $expTs);
                                        if ($now >= $graceStart) {
                                            $expStatus = 'expiring';
                                            $expLabel = __('user.domains.expiring_soon');
                                        } else {
                                            $expStatus = 'valid';
                                            $expLabel = __('user.domains.status_normal');
                                        }
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars(dns_domain_display_name($row)) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= match ((int) $row['status']) {1 => 'bg-slate-100 text-slate-600', 2 => 'bg-emerald-100 text-emerald-700', 3 => 'bg-amber-100 text-amber-700', 0 => 'bg-red-100 text-red-700', default => 'bg-slate-100 text-slate-600'} ?>">
                                            <?= htmlspecialchars(match ((int) $row['status']) {1 => __('user.domains.status_idle'), 2 => __('user.domains.status_in_use'), 3 => __('user.domains.status_reviewing'), 0 => __('user.domains.status_disabled'), default => __('user.domains.status_unknown')}) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($expiresAt === null): ?>
                                            <span class="text-xs text-slate-400"><?= __('user.domains.permanent') ?></span>
                                        <?php else: ?>
                                            <span class="text-xs <?= $expStatus === 'expired' ? 'text-red-600 font-medium' : ($expStatus === 'expiring' ? 'text-amber-600 font-medium' : 'text-slate-500') ?>">
                                                <?= htmlspecialchars(date('Y-m-d', strtotime($expiresAt))) ?>
                                            </span>
                                            <?php if ($expStatus === 'expired'): ?>
                                                <span class="ml-1 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700"><?= __('user.domains.expired') ?></span>
                                            <?php elseif ($expStatus === 'expiring'): ?>
                                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700"><?= __('user.domains.expiring_soon') ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['remark'] ?? '') ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($enableNsRecords): ?>
                                            <a href="/user/domains/?manage_ns=<?= (int) $row['id'] ?>" class="text-sm font-medium text-brand-600 hover:text-brand-800"><?= __('user.domains.manage_ns') ?></a>
                                            <span class="mx-2 text-slate-300">|</span>
                                        <?php endif; ?>
                                        <?php if ($enableTxtRecords): ?>
                                            <a href="/user/domains/?manage_txt=<?= (int) $row['id'] ?>" class="text-sm font-medium text-brand-600 hover:text-brand-800"><?= __('user.domains.manage_txt') ?></a>
                                            <span class="mx-2 text-slate-300">|</span>
                                        <?php endif; ?>
                                        <a href="/user/domains/?manage_dns=<?= (int) $row['id'] ?>" class="text-sm font-medium text-brand-600 hover:text-brand-800"><?= __('user.domains.manage_dns') ?></a>
                                        <?php if ($expStatus === 'expiring' && $renewalMonths > 0): ?>
                                            <span class="mx-2 text-slate-300">|</span>
                                            <form method="post" class="inline" onsubmit="return confirm('<?= __('user.domains.confirm_renew', ['months' => $renewalMonths]) ?>')">
                                                <input type="hidden" name="action" value="renew">
                                                <input type="hidden" name="domain_id" value="<?= (int) $row['id'] ?>">
                                                <button type="submit" class="text-sm font-medium text-amber-600 hover:text-amber-800"><?= __('user.domains.renew') ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php
});

<?php
require __DIR__ . '/../layout.php';

$pdo = auth_db();
$userId = (int) $_SESSION['user_id'];

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? 'success';

$stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.assigned_to = :user_id ORDER BY d.id DESC');
$stmt->execute([':user_id' => $userId]);
$rows = $stmt->fetchAll();

$manageDomainId = isset($_GET['manage_ns']) ? (int) $_GET['manage_ns'] : (isset($_GET['manage_txt']) ? (int) $_GET['manage_txt'] : 0);
$manageType = isset($_GET['manage_ns']) ? 'ns' : (isset($_GET['manage_txt']) ? 'txt' : '');
$manageDomain = null;
$nsRecords = [];
$txtRecords = [];
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
        } else {
            $txtRecords = dns_txt_records_by_domain_id($manageDomainId);
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
        $redirect .= '?message=' . urlencode('域名不属于当前用户') . '&type=error';
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
    }

    header('Location: ' . $redirect);
    exit;
}

$title = $manageDomain ? ($manageType === 'ns' ? 'NS' : 'TXT') . ' 记录管理 - ' . htmlspecialchars(dns_domain_display_name($manageDomain)) : '我的域名';
$activeKey = 'domains';

user_render($title, $activeKey, function () use ($rows, $manageDomain, $manageDomainId, $manageType, $nsRecords, $txtRecords, $message, $messageType, $pdo, $userId): void {
    ?>
    <section class="panel">
        <?php if ($manageDomain && $manageType === 'ns'): ?>
            <div class="flex items-center justify-between">
                <div>
                    <a href="/user/domains/" class="text-sm text-brand-600 hover:text-brand-700">&larr; 返回域名列表</a>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-900">NS 记录管理</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        域名：<strong><?= htmlspecialchars(dns_domain_display_name($manageDomain)) ?></strong>
                        （<?= count($nsRecords) ?>/5 条记录）
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
                            <th class="px-4 py-3 font-medium">同步状态</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($nsRecords)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400">暂无 NS 记录</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($nsRecords as $i => $ns): ?>
                                <tr>
                                    <td class="px-4 py-3 text-slate-400"><?= $i + 1 ?></td>
                                    <td class="px-4 py-3 font-mono text-slate-900"><?= htmlspecialchars($ns['nameserver']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($ns['provider_record_id'])): ?>
                                            <span class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">已同步</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full border border-yellow-200 bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700">仅本地</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <form method="post" action="/user/domains/?manage_ns=<?= $manageDomainId ?>" onsubmit="return confirm('确定删除这条 NS 记录？')">
                                            <input type="hidden" name="action" value="delete_ns">
                                            <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                                            <input type="hidden" name="record_id" value="<?= (int) $ns['id'] ?>">
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">删除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($nsRecords) < 5): ?>
                <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-slate-900">添加 NS 记录</h2>
                    <form method="post" action="/user/domains/?manage_ns=<?= $manageDomainId ?>" class="mt-4 flex flex-wrap items-end gap-4">
                        <input type="hidden" name="action" value="add_ns">
                        <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-700">Nameserver</label>
                            <input type="text" name="nameserver" required placeholder="例如 ns1.example.com" class="mt-1 block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <button type="submit" class="btn-primary">添加</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="mt-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                    已达到最大记录数上限（5 条）。如需修改，请先删除现有记录。
                </div>
            <?php endif; ?>

        <?php elseif ($manageDomain && $manageType === 'txt'): ?>
            <div class="flex items-center justify-between">
                <div>
                    <a href="/user/domains/" class="text-sm text-brand-600 hover:text-brand-700">&larr; 返回域名列表</a>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-900">TXT 记录管理</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        域名：<strong><?= htmlspecialchars(dns_domain_display_name($manageDomain)) ?></strong>
                        （<?= count($txtRecords) ?>/3 条记录）
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
                            <th class="px-4 py-3 font-medium">记录值</th>
                            <th class="px-4 py-3 font-medium">同步状态</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($txtRecords)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400">暂无 TXT 记录</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($txtRecords as $i => $tr): ?>
                                <tr>
                                    <td class="px-4 py-3 text-slate-400"><?= $i + 1 ?></td>
                                    <td class="px-4 py-3 font-mono text-slate-900 break-all"><?= htmlspecialchars($tr['value']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($tr['provider_record_id'])): ?>
                                            <span class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">已同步</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full border border-yellow-200 bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700">仅本地</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <form method="post" action="/user/domains/?manage_txt=<?= $manageDomainId ?>" onsubmit="return confirm('确定删除这条 TXT 记录？')">
                                            <input type="hidden" name="action" value="delete_txt">
                                            <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                                            <input type="hidden" name="record_id" value="<?= (int) $tr['id'] ?>">
                                            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">删除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($txtRecords) < 3): ?>
                <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5">
                    <h2 class="text-base font-semibold text-slate-900">添加 TXT 记录</h2>
                    <form method="post" action="/user/domains/?manage_txt=<?= $manageDomainId ?>" class="mt-4 flex flex-wrap items-end gap-4">
                        <input type="hidden" name="action" value="add_txt">
                        <input type="hidden" name="domain_id" value="<?= $manageDomainId ?>">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-700">记录值</label>
                            <input type="text" name="value" required placeholder="例如 v=spf1 include:_spf.google.com ~all" class="mt-1 block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        </div>
                        <button type="submit" class="btn-primary">添加</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="mt-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                    已达到最大记录数上限（3 条）。如需修改，请先删除现有记录。
                </div>
            <?php endif; ?>

        <?php else: ?>
            <h1 class="text-2xl font-semibold text-slate-900">我的域名</h1>
            <p class="mt-3 text-sm text-slate-600">这里显示当前分配给你的域名。</p>

            <?php if ($message): ?>
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= $messageType === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">完整域名</th>
                            <th class="px-4 py-3 font-medium">状态</th>
                            <th class="px-4 py-3 font-medium">备注</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars(dns_domain_display_name($row)) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars(match ((int) $row['status']) {1 => '空闲', 2 => '使用中', 3 => '审核中', 0 => '停用', default => '未知'}) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['remark'] ?? '') ?></td>
                                <td class="px-4 py-3">
                                    <a href="/user/domains/?manage_ns=<?= (int) $row['id'] ?>" class="text-sm font-medium text-brand-600 hover:text-brand-800">NS 管理</a>
                                    <span class="mx-2 text-slate-300">|</span>
                                    <a href="/user/domains/?manage_txt=<?= (int) $row['id'] ?>" class="text-sm font-medium text-brand-600 hover:text-brand-800">TXT 管理</a>
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

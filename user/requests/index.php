<?php
require __DIR__ . '/../layout.php';

$pdo = auth_db();
$error = '';
$message = '';
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $rootDomainId = (int) ($_POST['root_domain_id'] ?? 0);
            $subdomain = trim($_POST['subdomain'] ?? '');
            $purpose = trim($_POST['purpose'] ?? '');
            $remark = trim($_POST['remark'] ?? '');

            $user = auth_user_by_id($userId);
            if (!$user || !auth_user_has_whois($user)) {
                throw new RuntimeException('Please complete WHOIS info first.');
            }

            if ($rootDomainId <= 0 || $subdomain === '') {
                throw new RuntimeException('Please fill all fields.');
            }

            if (str_contains($subdomain, '.')) {
                throw new RuntimeException('仅支持二级域名，子域名前缀不能包含点号（.）。');
            }

            $root = dns_root_domain_by_id($rootDomainId);
            if (!$root || (int) $root['status'] !== 1) {
                throw new RuntimeException('Selected root domain is unavailable.');
            }

            $requestedDomain = dns_full_domain((string) $root['root_domain'], $subdomain);
            $pdo->beginTransaction();

            // Auto-create domain record if it doesn't exist in the pool
            $insert = $pdo->prepare('INSERT IGNORE INTO domains (root_domain_id, subdomain, status) VALUES (:root_domain_id, :subdomain, 1)');
            $insert->execute([
                ':root_domain_id' => $rootDomainId,
                ':subdomain' => $subdomain,
            ]);

            // Lock and verify the domain is available
            $stmt = $pdo->prepare('SELECT id, status FROM domains WHERE root_domain_id = :root_domain_id AND subdomain = :subdomain LIMIT 1 FOR UPDATE');
            $stmt->execute([
                ':root_domain_id' => $rootDomainId,
                ':subdomain' => $subdomain,
            ]);
            $domain = $stmt->fetch();
            if (!$domain || (int) $domain['status'] !== 1) {
                $reason = '';
                if ($domain) {
                    $reason = match ((int) $domain['status']) {
                        2 => ' (already assigned)',
                        3 => ' (pending review)',
                        0 => ' (disabled)',
                        default => '',
                    };
                }
                throw new RuntimeException('This domain is no longer available.' . $reason);
            }

            $stmt = $pdo->prepare('INSERT INTO domain_requests (user_id, domain_id, requested_domain, purpose, remark, status, created_at, updated_at) VALUES (:user_id, :domain_id, :requested_domain, :purpose, :remark, 2, NOW(), NOW())');
            $stmt->execute([
                ':user_id' => $userId,
                ':domain_id' => (int) $domain['id'],
                ':requested_domain' => $requestedDomain,
                ':purpose' => $purpose,
                ':remark' => $remark,
            ]);

            $stmt = $pdo->prepare('UPDATE domains SET status = 2, assigned_to = :assigned_to, updated_at = NOW() WHERE id = :id');
            $stmt->execute([':id' => (int) $domain['id'], ':assigned_to' => $userId]);

            $pdo->commit();
            $message = 'Submitted.';
        } elseif ($action === 'revoke') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM domain_requests WHERE id = :id AND user_id = :user_id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $id, ':user_id' => $userId]);
            $request = $stmt->fetch();
            if (!$request || (int) $request['status'] !== 1) {
                throw new RuntimeException('This request cannot be revoked.');
            }

            if (!empty($request['domain_id'])) {
                $stmt = $pdo->prepare('UPDATE domains SET status = 1, assigned_to = NULL, updated_at = NOW() WHERE id = :id');
                $stmt->execute([':id' => (int) $request['domain_id']]);
            }

            $stmt = $pdo->prepare('UPDATE domain_requests SET status = 4, updated_at = NOW() WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $pdo->commit();
            $message = 'Revoked.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$requests = $pdo->prepare('SELECT * FROM domain_requests WHERE user_id = :user_id ORDER BY id DESC');
$requests->execute([':user_id' => $userId]);
$rows = $requests->fetchAll();
$rootDomains = dns_root_domains();

user_render('Requests', 'requests', function () use ($rows, $rootDomains, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">申请</h1>
            <p class="mt-3 text-sm text-slate-600">提交前，WHOIS 信息必须完整。</p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">域名</th>
                            <th class="px-4 py-3 font-medium">理由</th>
                            <th class="px-4 py-3 font-medium">状态</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($row['requested_domain']) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['purpose'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars(match ((int) $row['status']) {1 => 'Pending', 2 => 'Approved', 3 => 'Rejected', 4 => 'Revoked', default => 'Unknown'}) ?></td>
                                <td class="px-4 py-3">
                                    <?php if ((int) $row['status'] === 1): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button class="text-red-600 hover:text-red-700" type="submit">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900">提交</h2>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">主域名</label>
                    <select name="root_domain_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="">请选择</option>
                        <?php foreach ($rootDomains as $root): ?>
                            <option value="<?= (int) $root['id'] ?>"><?= htmlspecialchars($root['root_domain']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">子域名前缀</label>
                    <input name="subdomain" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="api">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">理由</label>
                    <input name="purpose" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="Purpose">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">备注</label>
                    <textarea name="remark" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"></textarea>
                </div>
                <button type="submit" class="btn-primary w-full justify-center">提交</button>
            </form>
        </section>
    </div>
    <?php
});

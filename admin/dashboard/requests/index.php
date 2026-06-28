<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';

function admin_request_status_label(int $status): string
{
    return match ($status) {
        1 => 'Pending',
        2 => 'Approved',
        3 => 'Rejected',
        4 => 'Revoked',
        default => 'Unknown',
    };
}

function format_domain_display(string $domain): string
{
    $parts = explode('.', $domain);
    $top_level = ['com', 'net', 'org', 'edu', 'gov', 'mil', 'int', 'io', 'cn', 'uk', 'de', 'jp', 'fr', 'au', 'ca', 'br', 'in', 'ru', 'za', 'kr', 'nl', 'br', 'es', 'it', 'se', 'no', 'fi', 'dk', 'ch', 'at', 'be', 'pl', 'pt', 'gr', 'ir', 'il', 'ae', 'sa', 'my', 'sg', 'th', 'vn', 'tw', 'hk', 'mo'];
    
    if (strpos($domain, '.') !== false && in_array(strtolower($parts[0]), $top_level)) {
        return implode('.', array_reverse($parts));
    }
    
    return $domain;
}

$pdo = auth_db();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = (int) ($_POST['id'] ?? 0);

    try {
        if ($requestId <= 0) {
            throw new RuntimeException('Invalid request id.');
        }

        $pdo->beginTransaction();
        $locked = $pdo->prepare('SELECT * FROM domain_requests WHERE id = :id LIMIT 1 FOR UPDATE');
        $locked->execute([':id' => $requestId]);
        $request = $locked->fetch();
        if (!$request) {
            throw new RuntimeException('Request not found.');
        }

        if ($action === 'approve') {
            $domainId = (int) ($request['domain_id'] ?? 0);
            if ($domainId <= 0) {
                $lookup = $pdo->prepare(
                    'SELECT d.id
                     FROM domains d
                     INNER JOIN root_domains r ON r.id = d.root_domain_id
                     WHERE CONCAT(CASE WHEN d.subdomain = "" OR d.subdomain = "@" THEN "" ELSE CONCAT(d.subdomain, ".") END, r.root_domain) = :requested_domain
                     LIMIT 1 FOR UPDATE'
                );
                $lookup->execute([':requested_domain' => $request['requested_domain']]);
                $domainId = (int) $lookup->fetchColumn();
            }
            if ($domainId <= 0) {
                throw new RuntimeException('No domain available.');
            }

            $bind = $pdo->prepare('UPDATE domains SET status = 2, assigned_to = :assigned_to, updated_at = NOW() WHERE id = :id');
            $bind->execute([
                ':assigned_to' => (int) $request['user_id'],
                ':id' => $domainId,
            ]);

            $update = $pdo->prepare('UPDATE domain_requests SET domain_id = :domain_id, status = 2, reviewed_by = :reviewed_by, reviewed_at = NOW(), updated_at = NOW() WHERE id = :id');
            $update->execute([
                ':domain_id' => $domainId,
                ':reviewed_by' => (int) ($_SESSION['admin_id'] ?? 0),
                ':id' => $requestId,
            ]);

            $message = 'Approved.';
        } elseif ($action === 'reject' || $action === 'revoke') {
            if (!empty($request['domain_id'])) {
                $release = $pdo->prepare('UPDATE domains SET status = 1, assigned_to = NULL, updated_at = NOW() WHERE id = :id');
                $release->execute([':id' => (int) $request['domain_id']]);
            }

            $update = $pdo->prepare('UPDATE domain_requests SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW(), updated_at = NOW() WHERE id = :id');
            $update->execute([
                ':status' => $action === 'reject' ? 3 : 4,
                ':reviewed_by' => (int) ($_SESSION['admin_id'] ?? 0),
                ':id' => $requestId,
            ]);

            $message = $action === 'reject' ? 'Rejected.' : 'Revoked.';
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$rows = $pdo->query(
    'SELECT dr.*, u.username AS user_name, r.root_domain, d.subdomain
     FROM domain_requests dr
     LEFT JOIN users u ON u.id = dr.user_id
     LEFT JOIN domains d ON d.id = dr.domain_id
     LEFT JOIN root_domains r ON r.id = d.root_domain_id
     ORDER BY dr.id DESC'
)->fetchAll();

admin_dashboard_render('Requests', 'requests', function () use ($rows, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">申请</h1>
            <p class="mt-3 text-sm text-slate-600">批准会更新域名池状态。</p>

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
                            <th class="px-4 py-3 font-medium">用户</th>
                            <th class="px-4 py-3 font-medium">已申请的完整域名</th>
                            <th class="px-4 py-3 font-medium">理由</th>
                            <th class="px-4 py-3 font-medium">状态</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($row['user_name'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-900"><?= htmlspecialchars(format_domain_display($row['requested_domain'])) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['purpose'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars(admin_request_status_label((int) $row['status'])) ?></td>
                                <td class="px-4 py-3 space-x-2">
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="text-brand-700 hover:text-brand-800" type="submit">Approve</button>
                                    </form>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="text-red-600 hover:text-red-700" type="submit">Reject</button>
                                    </form>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="revoke">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="text-slate-500 hover:text-slate-700" type="submit">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900">申请说明</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li>记录来源于 `domain_requests` 表。</li>
                <li>Approve 后，回写 `domain_requests.domain_id` 并更新  `domains` 表。</li>
                <li>Reject or revoke 会释放已分配的域名。</li>
            </ul>
        </section>
    </div>
    <?php
});
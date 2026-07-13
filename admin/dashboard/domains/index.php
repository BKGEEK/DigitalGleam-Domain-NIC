<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../module/dns/api.php';

$error = '';
$message = '';

$rootDomains = dns_api_root_domain_list();
$selectedRootId = (int) ($_GET['root_domain_id'] ?? 0);
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $rootDomainId = (int) ($_POST['root_domain_id'] ?? 0);
            $subdomain = trim($_POST['subdomain'] ?? '');
            $status = (int) ($_POST['status'] ?? 1);
            $assignedTo = trim($_POST['assigned_to'] ?? '');
            $remark = trim($_POST['remark'] ?? '');

            if ($rootDomainId <= 0 || $subdomain === '') {
                throw new RuntimeException('请填写主域名和子域名前缀。');
            }

            dns_api_domain_create($rootDomainId, $subdomain, $status, $assignedTo !== '' ? (int) $assignedTo : null, $remark);
            $message = '域名已添加。';
            $selectedRootId = $rootDomainId;
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $rootDomainId = (int) ($_POST['root_domain_id'] ?? 0);
            $subdomain = trim($_POST['subdomain'] ?? '');
            $status = (int) ($_POST['status'] ?? 1);
            $assignedTo = trim($_POST['assigned_to'] ?? '');
            $remark = trim($_POST['remark'] ?? '');

            if ($id <= 0 || $rootDomainId <= 0 || $subdomain === '') {
                throw new RuntimeException('参数不完整。');
            }

            dns_api_domain_update($id, [
                'root_domain_id' => $rootDomainId,
                'subdomain' => $subdomain,
                'status' => $status,
                'assigned_to' => $assignedTo !== '' ? (int) $assignedTo : null,
                'remark' => $remark,
            ]);
            $message = '域名已更新。';
            $selectedRootId = $rootDomainId;
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('参数不完整。');
            }
            dns_api_domain_delete($id);
            $message = '域名已删除。';
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = (int) ($_POST['status'] ?? 1);
            dns_api_domain_toggle_status($id, $status);
            $message = '域名状态已更新。';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$domains = dns_api_domain_list($selectedRootId > 0 ? $selectedRootId : null);
if ($editId > 0) {
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider AS root_provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $editRow = $stmt->fetch();
}

admin_dashboard_render('域名池管理', 'domains', function () use ($domains, $rootDomains, $selectedRootId, $error, $message, $editRow): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">域名池</h1>
                    <p class="mt-2 text-sm text-slate-600">按主域名管理子域名前缀、状态和归属。</p>
                </div>
            </div>

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
                            <th class="px-4 py-3 font-medium">完整域名</th>
                            <th class="px-4 py-3 font-medium">主域名</th>
                            <th class="px-4 py-3 font-medium">状态</th>
                            <th class="px-4 py-3 font-medium">归属</th>
                            <th class="px-4 py-3 font-medium">备注</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($domains as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars(dns_domain_display_name($row)) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['root_domain'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-600">
                                    <?= (int) $row['status'] === 1 ? '空闲' : ((int) $row['status'] === 2 ? '使用中' : ((int) $row['status'] === 3 ? '审核中' : '停用')) ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($row['assigned_to'] ?? '')) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['remark'] ?? '') ?></td>
                                <td class="px-4 py-3 space-x-3">
                                    <a href="?edit=<?= (int) $row['id'] ?>&amp;root_domain_id=<?= (int) $row['root_domain_id'] ?>" class="text-sm font-medium text-brand-700 hover:text-brand-800">编辑</a>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="status" value="<?= (int) $row['status'] === 1 ? 2 : 1 ?>">
                                        <button type="submit" class="text-sm font-medium text-brand-700 hover:text-brand-800">切换</button>
                                    </form>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900"><?= $editRow ? '编辑域名' : '新增域名' ?></h2>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
                <?php endif; ?>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">主域名</label>
                    <select name="root_domain_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="">请选择</option>
                        <?php foreach ($rootDomains as $root): ?>
                            <option value="<?= (int) $root['id'] ?>" <?= ($editRow ? (int) $editRow['root_domain_id'] : $selectedRootId) === (int) $root['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($root['root_domain']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">子域名前缀</label>
                    <input name="subdomain" value="<?= htmlspecialchars($editRow['subdomain'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="api / cdn / img">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">状态</label>
                    <select name="status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="1" <?= (int) ($editRow['status'] ?? 1) === 1 ? 'selected' : '' ?>>空闲</option>
                        <option value="2" <?= (int) ($editRow['status'] ?? 1) === 2 ? 'selected' : '' ?>>使用中</option>
                        <option value="3" <?= (int) ($editRow['status'] ?? 1) === 3 ? 'selected' : '' ?>>审核中</option>
                        <option value="0" <?= (int) ($editRow['status'] ?? 1) === 0 ? 'selected' : '' ?>>停用</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">归属用户 ID</label>
                    <input name="assigned_to" value="<?= htmlspecialchars((string) ($editRow['assigned_to'] ?? '')) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="可留空">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">备注</label>
                    <textarea name="remark" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"><?= htmlspecialchars($editRow['remark'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><?= $editRow ? '保存修改' : '添加域名' ?></button>
            </form>
        </section>
    </div>
    <?php
});

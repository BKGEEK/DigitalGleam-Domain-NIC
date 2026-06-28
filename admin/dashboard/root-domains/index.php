<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../module/dns/api.php';

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $rootDomain = trim($_POST['root_domain'] ?? '');
            $provider = trim($_POST['provider'] ?? 'manual');
            $status = (int) ($_POST['status'] ?? 1);
            $remark = trim($_POST['remark'] ?? '');

            if ($rootDomain === '') {
                throw new RuntimeException('请填写主域名。');
            }

            dns_api_root_domain_create($rootDomain, $provider, $remark);
            if ($status === 0) {
                $list = dns_api_root_domain_list();
                foreach ($list as $row) {
                    if ($row['root_domain'] === $rootDomain) {
                        dns_api_root_domain_update((int) $row['id'], ['status' => 0]);
                        break;
                    }
                }
            }
            $message = '主域名已添加。';
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $rootDomain = trim($_POST['root_domain'] ?? '');
            $provider = trim($_POST['provider'] ?? 'manual');
            $status = (int) ($_POST['status'] ?? 1);
            $remark = trim($_POST['remark'] ?? '');

            if ($id <= 0 || $rootDomain === '') {
                throw new RuntimeException('参数不完整。');
            }

            dns_api_root_domain_update($id, [
                'root_domain' => $rootDomain,
                'provider' => $provider,
                'status' => $status,
                'remark' => $remark,
            ]);
            $message = '主域名已更新。';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('参数不完整。');
            }
            dns_api_root_domain_delete($id);
            $message = '主域名已删除。';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$providers = dns_provider_map();
$list = dns_api_root_domain_list();
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
foreach ($list as $row) {
    if ((int) $row['id'] === $editId) {
        $editRow = $row;
        break;
    }
}

admin_dashboard_render('主域名管理', 'root-domains', function () use ($list, $providers, $error, $message, $editRow): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">主域名列表</h1>
                    <p class="mt-2 text-sm text-slate-600">管理接入的主域名及其 provider。</p>
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
                            <th class="px-4 py-3 font-medium">主域名</th>
                            <th class="px-4 py-3 font-medium">Provider</th>
                            <th class="px-4 py-3 font-medium">状态</th>
                            <th class="px-4 py-3 font-medium">备注</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($list as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($row['root_domain']) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($providers[$row['provider']] ?? $row['provider']) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= (int) $row['status'] === 1 ? '正常' : '停用' ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['remark'] ?? '') ?></td>
                                <td class="px-4 py-3 space-x-3">
                                    <a href="?edit=<?= (int) $row['id'] ?>" class="text-sm font-medium text-brand-700 hover:text-brand-800">编辑</a>
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
            <h2 class="text-xl font-semibold text-slate-900"><?= $editRow ? '编辑主域名' : '新增主域名' ?></h2>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
                <?php endif; ?>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">主域名</label>
                    <input name="root_domain" value="<?= htmlspecialchars($editRow['root_domain'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="example.com">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Provider</label>
                    <select name="provider" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <?php foreach ($providers as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= ($editRow['provider'] ?? 'manual') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">状态</label>
                    <select name="status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="1" <?= (int) ($editRow['status'] ?? 1) === 1 ? 'selected' : '' ?>>正常</option>
                        <option value="0" <?= (int) ($editRow['status'] ?? 1) === 0 ? 'selected' : '' ?>>停用</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">备注</label>
                    <textarea name="remark" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"><?= htmlspecialchars($editRow['remark'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><?= $editRow ? '保存修改' : '添加主域名' ?></button>
            </form>
        </section>
    </div>
    <?php
});

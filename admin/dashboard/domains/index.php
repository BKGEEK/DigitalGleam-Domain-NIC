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
                throw new RuntimeException(__('admin.domains.error_empty'));
            }

            dns_api_domain_create($rootDomainId, $subdomain, $status, $assignedTo !== '' ? (int) $assignedTo : null, $remark);
            $message = __('admin.domains.added');
            $selectedRootId = $rootDomainId;
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $rootDomainId = (int) ($_POST['root_domain_id'] ?? 0);
            $subdomain = trim($_POST['subdomain'] ?? '');
            $status = (int) ($_POST['status'] ?? 1);
            $assignedTo = trim($_POST['assigned_to'] ?? '');
            $remark = trim($_POST['remark'] ?? '');

            if ($id <= 0 || $rootDomainId <= 0 || $subdomain === '') {
                throw new RuntimeException(__('admin.domains.error_params'));
            }

            dns_api_domain_update($id, [
                'root_domain_id' => $rootDomainId,
                'subdomain' => $subdomain,
                'status' => $status,
                'assigned_to' => $assignedTo !== '' ? (int) $assignedTo : null,
                'remark' => $remark,
            ]);
            $message = __('admin.domains.updated');
            $selectedRootId = $rootDomainId;
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException(__('admin.domains.error_params'));
            }
            dns_api_domain_delete($id);
            $message = __('admin.domains.deleted');
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = (int) ($_POST['status'] ?? 1);
            dns_api_domain_toggle_status($id, $status);
            $message = __('admin.domains.status_updated');
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

admin_dashboard_render(__('admin.domains.title'), 'domains', function () use ($domains, $rootDomains, $selectedRootId, $error, $message, $editRow): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900"><?= __('admin.domains.heading') ?></h1>
                    <p class="mt-2 text-sm text-slate-600"><?= __('admin.domains.desc') ?></p>
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
                            <th class="px-4 py-3 font-medium"><?= __('admin.domains.full_domain') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.domains.root_domain') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.domains.status') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.domains.assigned_to') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.domains.remark') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.domains.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($domains as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars(dns_domain_display_name($row)) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['root_domain'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-600">
                                    <?= (int) $row['status'] === 1 ? __('admin.domains.status_idle') : ((int) $row['status'] === 2 ? __('admin.domains.status_in_use') : ((int) $row['status'] === 3 ? __('admin.domains.status_reviewing') : __('admin.domains.status_disabled'))) ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars((string) ($row['assigned_to'] ?? '')) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['remark'] ?? '') ?></td>
                                <td class="px-4 py-3 space-x-3">
                                    <a href="?edit=<?= (int) $row['id'] ?>&amp;root_domain_id=<?= (int) $row['root_domain_id'] ?>" class="text-sm font-medium text-brand-700 hover:text-brand-800"><?= __('admin.domains.edit') ?></a>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="status" value="<?= (int) $row['status'] === 1 ? 2 : 1 ?>">
                                        <button type="submit" class="text-sm font-medium text-brand-700 hover:text-brand-800"><?= __('admin.domains.toggle') ?></button>
                                    </form>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700"><?= __('admin.domains.delete') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900"><?= $editRow ? __('admin.domains.edit_heading') : __('admin.domains.add_heading') ?></h2>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
                <?php endif; ?>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.domains.root_domain') ?></label>
                    <select name="root_domain_id" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value=""><?= __('admin.domains.select_placeholder') ?></option>
                        <?php foreach ($rootDomains as $root): ?>
                            <option value="<?= (int) $root['id'] ?>" <?= ($editRow ? (int) $editRow['root_domain_id'] : $selectedRootId) === (int) $root['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($root['root_domain']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.domains.subdomain') ?></label>
                    <input name="subdomain" value="<?= htmlspecialchars($editRow['subdomain'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="api / cdn / img">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.domains.status') ?></label>
                    <select name="status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="1" <?= (int) ($editRow['status'] ?? 1) === 1 ? 'selected' : '' ?>><?= __('admin.domains.status_idle') ?></option>
                        <option value="2" <?= (int) ($editRow['status'] ?? 1) === 2 ? 'selected' : '' ?>><?= __('admin.domains.status_in_use') ?></option>
                        <option value="3" <?= (int) ($editRow['status'] ?? 1) === 3 ? 'selected' : '' ?>><?= __('admin.domains.status_reviewing') ?></option>
                        <option value="0" <?= (int) ($editRow['status'] ?? 1) === 0 ? 'selected' : '' ?>><?= __('admin.domains.status_disabled') ?></option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.domains.assigned_user_id') ?></label>
                    <input name="assigned_to" value="<?= htmlspecialchars((string) ($editRow['assigned_to'] ?? '')) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('admin.domains.assigned_placeholder') ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.domains.remark') ?></label>
                    <textarea name="remark" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"><?= htmlspecialchars($editRow['remark'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><?= $editRow ? __('admin.domains.save') : __('admin.domains.add') ?></button>
            </form>
        </section>
    </div>
    <?php
});

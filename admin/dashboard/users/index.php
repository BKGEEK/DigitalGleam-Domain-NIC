<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';

$pdo = auth_db();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['id'] ?? 0);

    try {
        if ($userId <= 0) {
            throw new RuntimeException(__('admin.users.error_params'));
        }

        if ($action === 'toggle') {
            $status = (int) ($_POST['status'] ?? 1);
            $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $userId]);
            $message = __('admin.users.status_updated');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = $pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll();

admin_dashboard_render(__('admin.users.title'), 'users', function () use ($rows, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900"><?= __('admin.users.title') ?></h1>
            <p class="mt-3 text-sm text-slate-600"><?= __('admin.users.desc') ?></p>

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
                            <th class="px-4 py-3 font-medium"><?= __('admin.users.username') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.users.email') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.users.status') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.users.verification') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.users.last_login') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.users.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($row['username']) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= (int) $row['status'] === 1 ? __('admin.users.status_normal') : __('admin.users.status_disabled') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= !empty($row['email_verified_at']) ? __('admin.users.verified') : __('admin.users.unverified') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['last_login_at'] ?? '') ?></td>
                                <td class="px-4 py-3">
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="status" value="<?= (int) $row['status'] === 1 ? 0 : 1 ?>">
                                        <button type="submit" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                                            <?= (int) $row['status'] === 1 ? __('admin.users.status_disabled') : __('admin.users.enable') ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900"><?= __('admin.users.info_heading') ?></h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li><?= __('admin.users.info_line1') ?></li>
                <li><?= __('admin.users.info_line2') ?></li>
                <li><?= __('admin.users.info_line3') ?></li>
            </ul>
        </section>
    </div>
    <?php
});

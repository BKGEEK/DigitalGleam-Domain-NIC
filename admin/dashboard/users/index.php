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
            throw new RuntimeException('参数不完整。');
        }

        if ($action === 'toggle') {
            $status = (int) ($_POST['status'] ?? 1);
            $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $userId]);
            $message = '用户状态已更新。';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = $pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll();

admin_dashboard_render('用户管理', 'users', function () use ($rows, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">用户管理</h1>
            <p class="mt-3 text-sm text-slate-600">这里管理用户列表、禁用和邮箱验证状态。</p>

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
                            <th class="px-4 py-3 font-medium">用户名</th>
                            <th class="px-4 py-3 font-medium">邮箱</th>
                            <th class="px-4 py-3 font-medium">状态</th>
                            <th class="px-4 py-3 font-medium">验证</th>
                            <th class="px-4 py-3 font-medium">最后登录</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($row['username']) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= (int) $row['status'] === 1 ? '正常' : '禁用' ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= !empty($row['email_verified_at']) ? '已验证' : '未验证' ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['last_login_at'] ?? '') ?></td>
                                <td class="px-4 py-3">
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="status" value="<?= (int) $row['status'] === 1 ? 0 : 1 ?>">
                                        <button type="submit" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                                            <?= (int) $row['status'] === 1 ? '禁用' : '启用' ?>
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
            <h2 class="text-xl font-semibold text-slate-900">字段说明</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li>用户名和邮箱来自 `users` 表。</li>
                <li>`email_verified_at` 为空表示未验证。</li>
                <li>`last_login_at` 记录最近登录时间。</li>
            </ul>
        </section>
    </div>
    <?php
});

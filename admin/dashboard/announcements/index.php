<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';

$pdo = auth_db();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $status = (int) ($_POST['status'] ?? 1);
            if ($title === '' || $content === '') {
                throw new RuntimeException(__('admin.announcements.error_empty'));
            }

            $stmt = $pdo->prepare('INSERT INTO announcements (title, content, status, published_by, published_at) VALUES (:title, :content, :status, :published_by, NOW())');
            $stmt->execute([
                ':title' => $title,
                ':content' => $content,
                ':status' => $status,
                ':published_by' => (int) ($_SESSION['admin_id'] ?? 0),
            ]);
            $message = __('admin.announcements.created');
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = (int) ($_POST['status'] ?? 1);
            $stmt = $pdo->prepare('UPDATE announcements SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $id]);
            $message = __('admin.announcements.status_updated');
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $message = __('admin.announcements.deleted');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = $pdo->query('SELECT a.*, u.username AS publisher_name FROM announcements a LEFT JOIN admin_users u ON u.id = a.published_by ORDER BY a.id DESC')->fetchAll();

admin_dashboard_render(__('admin.announcements.title'), 'announcements', function () use ($rows, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900"><?= __('admin.announcements.title') ?></h1>
            <p class="mt-3 text-sm text-slate-600"><?= __('admin.announcements.desc') ?></p>

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
                            <th class="px-4 py-3 font-medium"><?= __('admin.announcements.title_label') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.announcements.publisher') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.announcements.status') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.announcements.published_at') ?></th>
                            <th class="px-4 py-3 font-medium"><?= __('admin.announcements.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($row['title']) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['publisher_name'] ?? '') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= (int) $row['status'] === 1 ? __('admin.announcements.show') : __('admin.announcements.hide') ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['published_at'] ?? '') ?></td>
                                <td class="px-4 py-3 space-x-2">
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="status" value="<?= (int) $row['status'] === 1 ? 0 : 1 ?>">
                                        <button class="text-brand-700 hover:text-brand-800" type="submit"><?= (int) $row['status'] === 1 ? __('admin.announcements.hide') : __('admin.announcements.show') ?></button>
                                    </form>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="text-red-600 hover:text-red-700" type="submit"><?= __('admin.announcements.delete') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900"><?= __('admin.announcements.create_heading') ?></h2>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.announcements.title_label') ?></label>
                    <input name="title" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.announcements.content') ?></label>
                    <textarea name="content" rows="6" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"></textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.announcements.status') ?></label>
                    <select name="status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <option value="1"><?= __('admin.announcements.show') ?></option>
                        <option value="0"><?= __('admin.announcements.hide') ?></option>
                    </select>
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><?= __('admin.announcements.publish') ?></button>
            </form>
        </section>
    </div>
    <?php
});

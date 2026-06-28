<?php
require __DIR__ . '/../layout.php';

$oauthConfig = auth_config()['oauth'] ?? [];
$pdo = auth_db();
$stmt = $pdo->prepare('SELECT * FROM oauth_accounts WHERE user_id = :user_id ORDER BY id DESC');
$stmt->execute([':user_id' => (int) $_SESSION['user_id']]);
$accounts = $stmt->fetchAll();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $provider = trim($_POST['provider'] ?? '');

    try {
        if ($action === 'unlink') {
            $stmt = $pdo->prepare('DELETE FROM oauth_accounts WHERE user_id = :user_id AND provider = :provider');
            $stmt->execute([
                ':user_id' => (int) $_SESSION['user_id'],
                ':provider' => $provider,
            ]);
            $message = '绑定已解除。';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

user_render('OAuth 绑定', 'oauth', function () use ($oauthConfig, $accounts, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">OAuth 绑定</h1>
            <p class="mt-3 text-sm text-slate-600">可将第三方账号与当前用户绑定。</p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="mt-6 flex flex-wrap gap-3">
                <?php if (!empty($oauthConfig['github']['enabled'])): ?>
                    <a href="/module/oauth/login.php?provider=github&return=/user/oauth/" class="btn-secondary">绑定 GitHub</a>
                <?php endif; ?>
                <?php if (!empty($oauthConfig['google']['enabled'])): ?>
                    <a href="/module/oauth/login.php?provider=google&return=/user/oauth/" class="btn-secondary">绑定 Google</a>
                <?php endif; ?>
            </div>

            <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">平台</th>
                            <th class="px-4 py-3 font-medium">账号</th>
                            <th class="px-4 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($accounts as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($row['provider']) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($row['provider_email'] ?? $row['provider_name'] ?? '') ?></td>
                                <td class="px-4 py-3">
                                    <form method="post" class="inline">
                                        <input type="hidden" name="action" value="unlink">
                                        <input type="hidden" name="provider" value="<?= htmlspecialchars($row['provider']) ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-700">解除绑定</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900">说明</h2>
            <p class="mt-4 text-sm leading-7 text-slate-600">
                绑定后，系统会优先使用第三方账号识别当前用户；如果邮箱已存在，也会自动归并到现有账号。
            </p>
        </section>
    </div>
    <?php
});

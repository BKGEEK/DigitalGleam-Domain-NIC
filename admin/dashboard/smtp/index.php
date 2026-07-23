<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';
require_once __DIR__ . '/../../../mail/mailer.php';

$configPath = __DIR__ . '/../../../config/config.php';
$config = require $configPath;
$smtp = $config['smtp'] ?? [];
$site = $config['site'] ?? [];

$error = '';
$message = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test') {
        $testEmail = trim($_POST['test_email'] ?? '');
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $testResult = ['success' => false, 'message' => __('admin.smtp.error_test_email')];
        } else {
            $testResult = mail_test($testEmail);
        }
    } else {
        $input = $_POST['smtp'] ?? [];

        $newSmtp = [
            'enabled' => true,
            'host' => trim($input['host'] ?? ''),
            'port' => (int) ($input['port'] ?? 465),
            'encryption' => trim($input['encryption'] ?? 'ssl'),
            'username' => trim($input['username'] ?? ''),
            'password' => (string) ($input['password'] ?? ''),
            'from_email' => trim($input['from_email'] ?? ''),
            'from_name' => trim($input['from_name'] ?? ''),
            'reply_to' => trim($input['reply_to'] ?? ''),
        ];

        if ($newSmtp['password'] === '') {
            $newSmtp['password'] = $smtp['password'] ?? '';
        }

        $newConfig = $config;
        $newConfig['smtp'] = $newSmtp;
        $newConfig['site']['email_verify'] = !empty($_POST['site']['email_verify']);

        $export = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
        if (file_put_contents($configPath, $export) === false) {
            $error = __('admin.settings.error_write');
        } else {
            $config = $newConfig;
            $smtp = $newSmtp;
            $site = $newConfig['site'];
            $message = __('admin.smtp.saved');
        }
    }
}

admin_dashboard_render(__('admin.smtp.title'), 'smtp', function () use ($smtp, $site, $error, $message, $testResult): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900"><?= __('admin.smtp.heading') ?></h1>
            <p class="mt-3 text-sm text-slate-600">
                <?= __('admin.smtp.desc') ?>
                <?php if (empty($smtp['host']) || empty($smtp['username'])): ?>
                    <span class="ml-1 text-amber-600"><?= __('admin.smtp.fill_first') ?></span>
                <?php endif; ?>
            </p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.host') ?></label>
                        <input name="smtp[host]" value="<?= htmlspecialchars($smtp['host'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="smtp.example.com">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.port') ?></label>
                        <input name="smtp[port]" value="<?= htmlspecialchars((string) ($smtp['port'] ?? 465)) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.encryption') ?></label>
                        <select name="smtp[encryption]" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                            <option value="ssl" <?= ($smtp['encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="tls" <?= ($smtp['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="" <?= empty($smtp['encryption']) ? 'selected' : '' ?>><?= __('admin.smtp.encryption_none') ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.username') ?></label>
                        <input name="smtp[username]" value="<?= htmlspecialchars($smtp['username'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.password') ?></label>
                        <input type="password" name="smtp[password]" value="<?= htmlspecialchars($smtp['password'] ?? '') ?>" placeholder="<?= __('admin.smtp.keep_placeholder') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.from_email') ?></label>
                        <input name="smtp[from_email]" value="<?= htmlspecialchars($smtp['from_email'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.from_name') ?></label>
                        <input name="smtp[from_name]" value="<?= htmlspecialchars($smtp['from_name'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.reply_to') ?></label>
                        <input name="smtp[reply_to]" value="<?= htmlspecialchars($smtp['reply_to'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="site[email_verify]" value="0">
                    <input type="checkbox" name="site[email_verify]" value="1" <?= !empty($site['email_verify']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                    <span class="text-sm text-slate-700"><?= __('admin.smtp.email_verify') ?></span>
                </div>

                <button type="submit" class="btn-primary"><?= __('admin.smtp.save') ?></button>
            </form>
        </section>

        <section class="panel self-start">
            <h2 class="text-xl font-semibold text-slate-900"><?= __('admin.smtp.info_heading') ?></h2>
            <ul class="mt-4 space-y-4 text-sm text-slate-600">
                <li>
                    <strong class="text-slate-800"><?= __('admin.smtp.password_recovery') ?></strong><br>
                    <?= __('admin.smtp.recovery_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.smtp.registration_verify') ?></strong><br>
                    <?= __('admin.smtp.verify_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.smtp.password_protection') ?></strong><br>
                    <?= __('admin.smtp.password_desc') ?>
                </li>
            </ul>

            <hr class="my-6 border-slate-200">

            <h2 class="text-xl font-semibold text-slate-900"><?= __('admin.smtp.test_heading') ?></h2>
            <p class="mt-3 text-sm text-slate-600"><?= __('admin.smtp.test_desc') ?></p>

            <?php if ($testResult): ?>
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= $testResult['success'] ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                    <?= htmlspecialchars($testResult['message']) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="mt-4">
                <input type="hidden" name="action" value="test">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.smtp.test_recipient') ?></label>
                    <input type="email" name="test_email" required placeholder="you@example.com" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <button type="submit" class="btn-primary mt-4 w-full justify-center"><?= __('admin.smtp.test_send') ?></button>
            </form>
        </section>
    </div>
    <?php
});
<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';

$configPath = __DIR__ . '/../../../config/config.php';
$config = require $configPath;
$templates = $config['email_templates'] ?? [];

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newTemplates = [
        'register_subject' => trim($_POST['register_subject'] ?? ''),
        'register_body' => trim($_POST['register_body'] ?? ''),
    ];

    $newConfig = $config;
    $newConfig['email_templates'] = $newTemplates;

    $export = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    if (file_put_contents($configPath, $export) === false) {
        $error = __('admin.settings.error_write');
    } else {
        $config = $newConfig;
        $templates = $newTemplates;
        $message = __('admin.email_templates.saved');
    }
}

admin_dashboard_render(__('admin.email_templates.title'), 'email-templates', function () use ($templates, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900"><?= __('admin.email_templates.title') ?></h1>
            <p class="mt-3 text-sm text-slate-600"><?= __('admin.email_templates.desc') ?></p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-6">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.email_templates.subject') ?></label>
                    <input name="register_subject" value="<?= htmlspecialchars($templates['register_subject'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    <p class="mt-1 text-xs text-slate-400"><?= __('admin.email_templates.subject_placeholder') ?></p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.email_templates.body') ?></label>
                    <textarea name="register_body" rows="16" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-mono outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"><?= htmlspecialchars($templates['register_body'] ?? '') ?></textarea>
                    <p class="mt-1 text-xs text-slate-400">
                        <?= __('admin.email_templates.body_placeholder') ?>
                        <span class="ml-2 text-amber-600"><?= __('admin.email_templates.body_note') ?></span>
                    </p>
                </div>
                <button type="submit" class="btn-primary"><?= __('admin.email_templates.save') ?></button>
            </form>
        </section>

        <section class="panel self-start">
            <h2 class="text-xl font-semibold text-slate-900"><?= __('admin.email_templates.info_heading') ?></h2>
            <ul class="mt-4 space-y-4 text-sm text-slate-600">
                <li>
                    <strong class="text-slate-800"><?= __('admin.email_templates.placeholders') ?></strong><br>
                    <?= __('admin.email_templates.placeholders_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.email_templates.verify_link') ?></strong><br>
                    <?= __('admin.email_templates.verify_link_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.email_templates.html_format') ?></strong><br>
                    <?= __('admin.email_templates.html_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.email_templates.restore_default') ?></strong><br>
                    <?= __('admin.email_templates.restore_desc') ?>
                </li>
            </ul>
        </section>
    </div>
    <?php
});
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
        $error = '配置文件写入失败。';
    } else {
        $config = $newConfig;
        $templates = $newTemplates;
        $message = '邮件模板已保存。';
    }
}

admin_dashboard_render('邮件模板', 'email-templates', function () use ($templates, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">邮件模板</h1>
            <p class="mt-3 text-sm text-slate-600">自定义系统发送的邮件内容。使用 <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono text-brand-700">{verification_link}</code> 插入验证链接，<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono text-brand-700">{site_name}</code> 插入站点名称，<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono text-brand-700">{username}</code> 插入用户名。</p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-6">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">邮件主题（注册验证）</label>
                    <input name="register_subject" value="<?= htmlspecialchars($templates['register_subject'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    <p class="mt-1 text-xs text-slate-400">可用占位符：<code class="text-brand-600">{site_name}</code></p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">邮件正文（注册验证）</label>
                    <textarea name="register_body" rows="16" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-mono outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"><?= htmlspecialchars($templates['register_body'] ?? '') ?></textarea>
                    <p class="mt-1 text-xs text-slate-400">
                        可用占位符：<code class="text-brand-600">{verification_link}</code> <code class="text-brand-600">{site_name}</code> <code class="text-brand-600">{username}</code>
                        <span class="ml-2 text-amber-600">注意：{verification_link} 为系统自动生成，不可自定义。</span>
                    </p>
                </div>
                <button type="submit" class="btn-primary">保存模板</button>
            </form>
        </section>

        <section class="panel self-start">
            <h2 class="text-xl font-semibold text-slate-900">说明</h2>
            <ul class="mt-4 space-y-4 text-sm text-slate-600">
                <li>
                    <strong class="text-slate-800">占位符</strong><br>
                    模板中使用 <code class="text-brand-600">{verification_link}</code>、<code class="text-brand-600">{site_name}</code>、<code class="text-brand-600">{username}</code> 会被自动替换为实际值。
                </li>
                <li>
                    <strong class="text-slate-800">验证链接</strong><br>
                    <code class="text-brand-600">{verification_link}</code> 由系统生成，包含加密 token，无法通过模板自定义。你可以在模板中自由放置此占位符的位置。
                </li>
                <li>
                    <strong class="text-slate-800">HTML 格式</strong><br>
                    正文支持完整 HTML 标签，建议使用行内样式以确保兼容性。
                </li>
                <li>
                    <strong class="text-slate-800">恢复默认</strong><br>
                    如需恢复默认模板，清空所有字段并保存，系统会自动回退到默认值。
                </li>
            </ul>
        </section>
    </div>
    <?php
});
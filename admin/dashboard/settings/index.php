<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';

$configPath = __DIR__ . '/../../../config/config.php';
$config = require $configPath;
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app = $_POST['app'] ?? [];
    $site = $_POST['site'] ?? [];
    $domain = $_POST['domain'] ?? [];

    $newConfig = [
        'app' => [
            'name' => trim($app['name'] ?? ''),
            'timezone' => trim($app['timezone'] ?? 'Asia/Shanghai'),
            'debug' => !empty($app['debug']),
            'base_url' => trim($app['base_url'] ?? ''),
        ],
        'db' => $config['db'],
        'site' => [
            'status' => !empty($site['status']) ? 1 : 0,
            'notice' => trim($site['notice'] ?? ''),
        ],
        'domain' => [
            'min_length' => max(1, (int) ($domain['min_length'] ?? 3)),
            'max_length' => max(1, (int) ($domain['max_length'] ?? 24)),
            'allow_unicode' => !empty($domain['allow_unicode']),
            'auto_approve' => !empty($domain['auto_approve']),
            'max_domains_per_user' => max(0, (int) ($domain['max_domains_per_user'] ?? 3)),
            'max_ns_records' => max(0, (int) ($domain['max_ns_records'] ?? 5)),
            'max_txt_records' => max(0, (int) ($domain['max_txt_records'] ?? 3)),
            'max_a_records' => max(0, (int) ($domain['max_a_records'] ?? 10)),
            'max_aaaa_records' => max(0, (int) ($domain['max_aaaa_records'] ?? 10)),
            'max_cname_records' => max(0, (int) ($domain['max_cname_records'] ?? 10)),
        ],
        'smtp' => $config['smtp'],
        'dns' => $config['dns'],
        'oauth' => $config['oauth'],
    ];

    $export = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    if (file_put_contents($configPath, $export) === false) {
        $error = '配置文件写入失败。';
    } else {
        $config = $newConfig;
        $message = '设置已保存。';
    }
}

admin_dashboard_render('系统设置', 'settings', function () use ($config, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">系统设置</h1>
            <p class="mt-3 text-sm text-slate-600">配置站点基本信息和运行参数。</p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">站点名称</label>
                        <input name="app[name]" value="<?= htmlspecialchars($config['app']['name'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">时区</label>
                        <input name="app[timezone]" value="<?= htmlspecialchars($config['app']['timezone'] ?? 'Asia/Shanghai') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">基础 URL</label>
                        <input name="app[base_url]" value="<?= htmlspecialchars($config['app']['base_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div class="flex items-center gap-3 pt-8">
                        <input type="checkbox" name="app[debug]" value="1" <?= !empty($config['app']['debug']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <span class="text-sm text-slate-700">开启调试模式</span>
                    </div>
                </div>

                <div>
                    
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="site[status]" value="1" <?= !empty($config['site']['status']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                    <span class="text-sm text-slate-700">站点开启</span>
                </div>

                <hr class="border-slate-200">

                <h3 class="text-lg font-semibold text-slate-900">域名申请限制</h3>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">前缀最小长度</label>
                        <input name="domain[min_length]" type="number" min="1" value="<?= (int) ($config['domain']['min_length'] ?? 3) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">前缀最大长度</label>
                        <input name="domain[max_length]" type="number" min="1" value="<?= (int) ($config['domain']['max_length'] ?? 24) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">每人最大域名数</label>
                        <input name="domain[max_domains_per_user]" type="number" min="0" value="<?= (int) ($config['domain']['max_domains_per_user'] ?? 3) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="domain[allow_unicode]" value="1" <?= !empty($config['domain']['allow_unicode']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                    <span class="text-sm text-slate-700">允许特殊 Unicode 字符（汉字、emoji 等）</span>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="domain[auto_approve]" value="1" <?= !empty($config['domain']['auto_approve']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                    <span class="text-sm text-slate-700">域名申请自动通过</span>
                </div>

                <hr class="border-slate-200">

                <h3 class="text-lg font-semibold text-slate-900">DNS 记录数量限制</h3>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">NS 记录上限</label>
                        <input name="domain[max_ns_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_ns_records'] ?? 5) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">TXT 记录上限</label>
                        <input name="domain[max_txt_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_txt_records'] ?? 3) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">A 记录上限</label>
                        <input name="domain[max_a_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_a_records'] ?? 10) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">AAAA 记录上限</label>
                        <input name="domain[max_aaaa_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_aaaa_records'] ?? 10) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">CNAME 记录上限</label>
                        <input name="domain[max_cname_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_cname_records'] ?? 10) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <button type="submit" class="btn-primary">保存设置</button>
            </form>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900">配置说明</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li>邮件服务（SMTP）已移至独立页面「邮件服务」管理。</li>
                <li>DNS 接口已移至独立页面「DNS 接口」管理。</li>
                <li>第三方登录已移至独立页面「第三方登录」管理。</li>
            </ul>
        </section>
    </div>
    <?php
});
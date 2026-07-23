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
            'enable_ns_records' => !empty($domain['enable_ns_records']),
            'enable_txt_records' => !empty($domain['enable_txt_records']),
            'max_a_records' => max(0, (int) ($domain['max_a_records'] ?? 10)),
            'max_aaaa_records' => max(0, (int) ($domain['max_aaaa_records'] ?? 10)),
            'max_cname_records' => max(0, (int) ($domain['max_cname_records'] ?? 10)),
            'enable_a_records' => !empty($domain['enable_a_records']),
            'enable_aaaa_records' => !empty($domain['enable_aaaa_records']),
            'enable_cname_records' => !empty($domain['enable_cname_records']),
            'registration_months' => max(0, (int) ($domain['registration_months'] ?? 12)),
            'renewal_grace_months' => max(0, (int) ($domain['renewal_grace_months'] ?? 3)),
            'renewal_months' => max(0, (int) ($domain['renewal_months'] ?? 12)),
        ],
        'smtp' => $config['smtp'],
        'dns' => $config['dns'],
        'oauth' => $config['oauth'],
    ];

    $export = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    if (file_put_contents($configPath, $export) === false) {
        $error = __('admin.settings.error_write');
    } else {
        $config = $newConfig;
        $message = __('admin.settings.saved');
    }
}

admin_dashboard_render(__('admin.settings.title'), 'settings', function () use ($config, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900"><?= __('admin.settings.title') ?></h1>
            <p class="mt-3 text-sm text-slate-600"><?= __('admin.settings.desc') ?></p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.site_name') ?></label>
                        <input name="app[name]" value="<?= htmlspecialchars($config['app']['name'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.timezone') ?></label>
                        <input name="app[timezone]" value="<?= htmlspecialchars($config['app']['timezone'] ?? 'Asia/Shanghai') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.base_url') ?></label>
                        <input name="app[base_url]" value="<?= htmlspecialchars($config['app']['base_url'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div class="flex items-center gap-3 pt-8">
                        <input type="checkbox" name="app[debug]" value="1" <?= !empty($config['app']['debug']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <span class="text-sm text-slate-700"><?= __('admin.settings.debug_mode') ?></span>
                    </div>
                </div>

                <div>
                    
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="site[status]" value="1" <?= !empty($config['site']['status']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                    <span class="text-sm text-slate-700"><?= __('admin.settings.site_enabled') ?></span>
                </div>

                <hr class="border-slate-200">

                <h3 class="text-lg font-semibold text-slate-900"><?= __('admin.settings.domain_limits') ?></h3>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.min_length') ?></label>
                        <input name="domain[min_length]" type="number" min="1" value="<?= (int) ($config['domain']['min_length'] ?? 3) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.max_length') ?></label>
                        <input name="domain[max_length]" type="number" min="1" value="<?= (int) ($config['domain']['max_length'] ?? 24) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.max_domains') ?></label>
                        <input name="domain[max_domains_per_user]" type="number" min="0" value="<?= (int) ($config['domain']['max_domains_per_user'] ?? 3) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="domain[allow_unicode]" value="1" <?= !empty($config['domain']['allow_unicode']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                    <span class="text-sm text-slate-700"><?= __('admin.settings.allow_unicode') ?></span>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="domain[auto_approve]" value="1" <?= !empty($config['domain']['auto_approve']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                    <span class="text-sm text-slate-700"><?= __('admin.settings.auto_approve') ?></span>
                </div>

                <hr class="border-slate-200">

                <h3 class="text-lg font-semibold text-slate-900"><?= __('admin.settings.dns_limits') ?></h3>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.max_ns') ?></label>
                        <input name="domain[max_ns_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_ns_records'] ?? 5) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.max_txt') ?></label>
                        <input name="domain[max_txt_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_txt_records'] ?? 3) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.max_a') ?></label>
                        <input name="domain[max_a_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_a_records'] ?? 10) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.max_aaaa') ?></label>
                        <input name="domain[max_aaaa_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_aaaa_records'] ?? 10) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.max_cname') ?></label>
                        <input name="domain[max_cname_records]" type="number" min="0" value="<?= (int) ($config['domain']['max_cname_records'] ?? 10) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 mt-6"><?= __('admin.settings.dns_toggles') ?></h3>

                <div class="flex flex-wrap items-center gap-6">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="domain[enable_ns_records]" value="1" <?= !empty($config['domain']['enable_ns_records']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <span class="text-sm text-slate-700"><?= __('admin.settings.enable_ns') ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="domain[enable_txt_records]" value="1" <?= !empty($config['domain']['enable_txt_records']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <span class="text-sm text-slate-700"><?= __('admin.settings.enable_txt') ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="domain[enable_a_records]" value="1" <?= !empty($config['domain']['enable_a_records']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <span class="text-sm text-slate-700"><?= __('admin.settings.enable_a') ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="domain[enable_aaaa_records]" value="1" <?= !empty($config['domain']['enable_aaaa_records']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <span class="text-sm text-slate-700"><?= __('admin.settings.enable_aaaa') ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="domain[enable_cname_records]" value="1" <?= !empty($config['domain']['enable_cname_records']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <span class="text-sm text-slate-700"><?= __('admin.settings.enable_cname') ?></span>
                    </div>
                </div>

                <hr class="border-slate-200">

                <h3 class="text-lg font-semibold text-slate-900"><?= __('admin.settings.domain_validity') ?></h3>
                <p class="mt-1 text-sm text-slate-500"><?= __('admin.settings.zero_is_permanent') ?></p>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.registration_months') ?></label>
                        <input name="domain[registration_months]" type="number" min="0" value="<?= (int) ($config['domain']['registration_months'] ?? 12) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <p class="mt-1 text-xs text-slate-400"><?= __('admin.settings.registration_desc') ?></p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.renewal_grace_months') ?></label>
                        <input name="domain[renewal_grace_months]" type="number" min="0" value="<?= (int) ($config['domain']['renewal_grace_months'] ?? 3) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <p class="mt-1 text-xs text-slate-400"><?= __('admin.settings.grace_desc') ?></p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.settings.renewal_months') ?></label>
                        <input name="domain[renewal_months]" type="number" min="0" value="<?= (int) ($config['domain']['renewal_months'] ?? 12) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        <p class="mt-1 text-xs text-slate-400"><?= __('admin.settings.renewal_desc') ?></p>
                    </div>
                </div>

                <button type="submit" class="btn-primary"><?= __('admin.settings.save') ?></button>
            </form>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900"><?= __('admin.settings.info_heading') ?></h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li><?= __('admin.settings.info_line1') ?></li>
                <li><?= __('admin.settings.info_line2') ?></li>
                <li><?= __('admin.settings.info_line3') ?></li>
            </ul>
        </section>
    </div>
    <?php
});
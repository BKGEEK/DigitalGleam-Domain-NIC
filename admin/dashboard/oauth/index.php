<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';
require_once __DIR__ . '/../../../module/oauth/service.php';

$configPath = __DIR__ . '/../../../config/config.php';
$config = require $configPath;
$oauth = $config['oauth'] ?? [];
$providers = oauth_provider_map();

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['oauth'] ?? [];
    $newOauth = [];

    foreach ($providers as $key => $label) {
        $existing = $oauth[$key] ?? [];
        $providerInput = $input[$key] ?? [];

        $newOauth[$key] = array_merge($existing, $providerInput);
        $newOauth[$key]['enabled'] = !empty($providerInput['enabled']);

        // Keep existing secret if left blank
        foreach (['client_secret'] as $secretField) {
            if (isset($newOauth[$key][$secretField]) && $newOauth[$key][$secretField] === '') {
                $newOauth[$key][$secretField] = $existing[$secretField] ?? '';
            }
        }
    }

    $newConfig = $config;
    $newConfig['oauth'] = $newOauth;

    $export = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    if (file_put_contents($configPath, $export) === false) {
        $error = __('admin.settings.error_write');
    } else {
        $config = $newConfig;
        $oauth = $newOauth;
        $message = __('admin.oauth.saved');
    }
}

admin_dashboard_render(__('admin.oauth.title'), 'oauth', function () use ($oauth, $providers, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900"><?= __('admin.oauth.heading') ?></h1>
            <p class="mt-3 text-sm text-slate-600"><?= __('admin.oauth.desc') ?></p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-8">
                <?php foreach ($providers as $key => $label):
                    $providerConfig = $oauth[$key] ?? [];
                    $enabled = !empty($providerConfig['enabled']);
                    $autoCallback = oauth_callback_url($key);
                ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900"><?= htmlspecialchars($label) ?></h2>
                            <p class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($key) ?></p>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="hidden" name="oauth[<?= $key ?>][enabled]" value="0">
                            <input type="checkbox" name="oauth[<?= $key ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?> class="peer sr-only">
                            <div class="h-6 w-11 rounded-full bg-slate-300 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-brand-500 peer-checked:after:translate-x-full"></div>
                            <span class="ml-3 text-sm text-slate-600"><?= $enabled ? __('admin.dns.enabled') : __('admin.dns.disabled') ?></span>
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="oauth-<?= $key ?>-client_id" class="mb-2 block text-sm font-medium text-slate-700">Client ID</label>
                            <input type="text" id="oauth-<?= $key ?>-client_id" name="oauth[<?= $key ?>][client_id]" value="<?= htmlspecialchars((string) ($providerConfig['client_id'] ?? '')) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div>
                            <label for="oauth-<?= $key ?>-client_secret" class="mb-2 block text-sm font-medium text-slate-700">Client Secret</label>
                            <input type="password" id="oauth-<?= $key ?>-client_secret" name="oauth[<?= $key ?>][client_secret]" value="<?= htmlspecialchars((string) ($providerConfig['client_secret'] ?? '')) ?>" placeholder="<?= __('admin.smtp.keep_placeholder') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div class="md:col-span-2">
                            <label for="oauth-<?= $key ?>-redirect_uri" class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.oauth.redirect_uri') ?></label>
                            <input type="url" id="oauth-<?= $key ?>-redirect_uri" name="oauth[<?= $key ?>][redirect_uri]" value="<?= htmlspecialchars((string) ($providerConfig['redirect_uri'] ?? '')) ?>" placeholder="<?= __('admin.oauth.auto_placeholder') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                            <p class="mt-1.5 text-xs text-slate-400"><?= __('admin.oauth.auto_desc') ?><code class="rounded bg-slate-50 px-1.5 py-0.5"><?= htmlspecialchars($autoCallback) ?></code></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-primary"><?= __('admin.oauth.save') ?></button>
            </form>
        </section>

        <section class="panel self-start">
            <h2 class="text-xl font-semibold text-slate-900"><?= __('admin.oauth.info_heading') ?></h2>
            <ul class="mt-4 space-y-4 text-sm text-slate-600">
                <li>
                    <strong class="text-slate-800"><?= __('admin.oauth.github') ?></strong><br>
                    <?= __('admin.oauth.github_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.oauth.google') ?></strong><br>
                    <?= __('admin.oauth.google_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.oauth.nodeloc') ?></strong><br>
                    <?= __('admin.oauth.nodeloc_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.oauth.enable_conditions') ?></strong><br>
                    <?= __('admin.oauth.enable_desc') ?>
                </li>
                <li>
                    <strong class="text-slate-800"><?= __('admin.oauth.secret_protection') ?></strong><br>
                    <?= __('admin.oauth.secret_desc') ?>
                </li>
            </ul>
        </section>
    </div>
    <?php
});
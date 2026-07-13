<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../mail/mailer.php';
require_once __DIR__ . '/../../mail/template.php';

$userId = (int) $_SESSION['user_id'];
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nickname' => trim($_POST['nickname'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'whois_public' => !empty($_POST['whois_public']) ? 1 : 0,
            'whois_name' => trim($_POST['whois_name'] ?? ''),
            'whois_phone' => trim($_POST['whois_phone'] ?? ''),
            'whois_email' => trim($_POST['whois_email'] ?? ''),
            'whois_company' => trim($_POST['whois_company'] ?? ''),
            'whois_address' => trim($_POST['whois_address'] ?? ''),
            'whois_id_number' => trim($_POST['whois_id_number'] ?? ''),
        ];

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email.');
        }
        if ($data['email'] !== '' && !auth_validate_email_domain($data['email'])) {
            throw new RuntimeException('仅支持 gmail.com、qq.com、163.com、outlook.com 邮箱，不支持带 + 的别名邮箱。');
        }
        if ($data['whois_email'] !== '' && !filter_var($data['whois_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid WHOIS email.');
        }

        $oldUser = auth_user_by_id($userId);
        auth_update_user_profile($userId, $data);

        if ($oldUser && $data['email'] !== '' && strcasecmp((string) ($oldUser['email'] ?? ''), $data['email']) !== 0) {
            $verify = auth_reset_email_verification($userId, $data['email']);
            $baseUrl = rtrim((auth_config()['app']['base_url'] ?? ''), '/');
            $verifyLink = $baseUrl . '/user/register/verify.php?token=' . urlencode($verify['token']);
            mail_send($data['email'], 'Email verification', mail_template('Email verification', "Verify here:\n{$verifyLink}\n\nValid for 24 hours."));
            $message = 'Saved. Please verify the new email address.';
        } else {
            $message = 'Saved.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$user = auth_user_by_id($userId);

user_render('Profile', 'profile', function () use ($user, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">Profile</h1>
            <p class="mt-3 text-sm text-slate-600">Complete WHOIS info before requesting a domain.</p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Nickname</label>
                        <input name="nickname" value="<?= htmlspecialchars($user['nickname'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Email</label>
                        <input name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Phone</label>
                        <input name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-sm font-medium text-slate-900">WHOIS</div>
                    <div class="mt-4 flex items-center gap-3">
                        <input type="checkbox" name="whois_public" value="1" <?= !empty($user['whois_public']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <span class="text-sm text-slate-700">Public WHOIS</span>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Contact Name</label>
                            <input name="whois_name" value="<?= htmlspecialchars($user['whois_name'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Contact Phone</label>
                            <input name="whois_phone" value="<?= htmlspecialchars($user['whois_phone'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Contact Email</label>
                            <input name="whois_email" value="<?= htmlspecialchars($user['whois_email'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Company</label>
                            <input name="whois_company" value="<?= htmlspecialchars($user['whois_company'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Address</label>
                            <input name="whois_address" value="<?= htmlspecialchars($user['whois_address'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">ID Number</label>
                            <input name="whois_id_number" value="<?= htmlspecialchars($user['whois_id_number'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Save</button>
            </form>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900">Status</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li>Email verified: <?= !empty($user['email_verified_at']) ? 'yes' : 'no' ?></li>
                <li>WHOIS name: <?= !empty($user['whois_name']) ? 'set' : 'unset' ?></li>
                <li>WHOIS email: <?= !empty($user['whois_email']) ? 'set' : 'unset' ?></li>
                <li>WHOIS phone: <?= !empty($user['whois_phone']) ? 'set' : 'unset' ?></li>
            </ul>
        </section>
    </div>
    <?php
});

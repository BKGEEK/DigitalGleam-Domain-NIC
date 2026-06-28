<?php
$config = require __DIR__ . '/../../config/config.php';
$appName = $config['app']['name'] ?? '数星二级域名分发';
?>
</main>
    <footer class="border-t border-slate-200 bg-white mt-8">
        <div class="page-wrap py-6 text-sm text-slate-500">
            <div>© <?= date('Y') ?> <?= htmlspecialchars($appName) ?></div>
            <div class="mt-1">简约 · 清新 · 可扩展</div>
        </div>
    </footer>
</body>
</html>

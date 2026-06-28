<?php
if (!isset($pageTitle)) {
    $pageTitle = '数星二级域名分发';
}

// Load config for nav brand
$config = require __DIR__ . '/../../config/config.php';
$appName = $config['app']['name'] ?? '数星二级域名分发';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eefbf7',
                            100: '#d7f6eb',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .page-wrap {
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .panel {
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            padding: 1.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            background-color: #16a34a;
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #ffffff;
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
        .btn-primary:hover {
            background-color: #15803d;
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
        .btn-secondary:hover {
            background-color: #f1f5f9;
            color: #334155;
        }
        .badge-brand {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            border: 1px solid #d7f6eb;
            background-color: #eefbf7;
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
            color: #16a34a;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
<main>
    <!-- 导航栏 -->
    <section class="border-b border-slate-200 bg-white">
        <div class="page-wrap py-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <a href="/" class="text-xl font-semibold text-brand-600"><?= htmlspecialchars($appName) ?></a>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <a href="/whois/index.php" class="text-slate-600 hover:text-brand-600 transition-colors">WHOIS 查询</a>
                    <a href="/" class="text-slate-600 hover:text-brand-600 transition-colors">域名检索</a>
                    <a href="/#notice" class="text-slate-600 hover:text-brand-600 transition-colors">公告</a>
                    <a href="/user/login/" class="rounded-full bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors">用户登录</a>
                </div>
            </div>
        </div>
    </section>

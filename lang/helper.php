<?php

$lang_loaded = false;
$lang_current = 'zh-CN';
$lang_messages = [];

function lang_load(): void
{
    global $lang_loaded, $lang_current, $lang_messages;

    if ($lang_loaded) {
        return;
    }

    $lang_loaded = true;

    $supported = [
        'zh-CN', 'zh-TW', 'en', 'de', 'es', 'fr', 'pt', 'ja',
    ];

    $detected = 'zh-CN';

    if (!empty($_GET['lang'])) {
        $candidate = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['lang']);
        if (in_array($candidate, $supported, true)) {
            $detected = $candidate;
        }
    } elseif (!empty($_COOKIE['lang'])) {
        $candidate = preg_replace('/[^a-zA-Z0-9\-]/', '', $_COOKIE['lang']);
        if (in_array($candidate, $supported, true)) {
            $detected = $candidate;
        }
    } elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 5);
        $map = [
            'zh-TW' => 'zh-TW', 'zh-HK' => 'zh-TW', 'zh-MO' => 'zh-TW',
            'zh-CN' => 'zh-CN', 'zh-SG' => 'zh-CN',
            'en' => 'en', 'en-US' => 'en', 'en-GB' => 'en',
            'de' => 'de', 'de-DE' => 'de', 'de-AT' => 'de', 'de-CH' => 'de',
            'es' => 'es', 'es-ES' => 'es',
            'fr' => 'fr', 'fr-FR' => 'fr',
            'pt' => 'pt', 'pt-PT' => 'pt', 'pt-BR' => 'pt',
            'ja' => 'ja', 'ja-JP' => 'ja',
        ];
        foreach ($map as $key => $lang) {
            if (str_starts_with($browserLang, $key)) {
                $detected = $lang;
                break;
            }
        }
    }

    $lang_current = $detected;

    $path = __DIR__ . '/' . $lang_current . '.php';
    if (file_exists($path)) {
        $lang_messages = require $path;
    } else {
        $lang_messages = require __DIR__ . '/zh-CN.php';
    }
}

function __(string $key, array $params = []): string
{
    global $lang_messages;

    lang_load();

    $text = $lang_messages[$key] ?? $key;

    if (!empty($params)) {
        foreach ($params as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
    }

    return $text;
}

function lang_current(): string
{
    global $lang_current;
    lang_load();
    return $lang_current;
}

function lang_switch_url(string $targetLang): string
{
    $query = $_GET;
    unset($query['lang']);
    $query['lang'] = $targetLang;
    $base = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $base . '?' . http_build_query($query);
}

function lang_selector(): string
{
    $current = lang_current();
    $labels = [
        'zh-CN' => '简体中文',
        'zh-TW' => '繁體中文',
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'fr' => 'Français',
        'pt' => 'Português',
        'ja' => '日本語',
    ];

    $html = '<div class="relative inline-block text-left" id="lang-selector">';
    $html .= '<button onclick="document.getElementById(\'lang-menu\').classList.toggle(\'hidden\')" class="flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50 transition-colors">';
    $html .= '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    $html .= htmlspecialchars($labels[$current] ?? $current);
    $html .= '</button>';
    $html .= '<div id="lang-menu" class="hidden absolute right-0 z-50 mt-1 w-36 rounded-2xl border border-slate-200 bg-white py-1 shadow-lg text-xs">';
    foreach ($labels as $code => $label) {
        $active = $code === $current ? ' bg-brand-50 text-brand-700 font-medium' : ' text-slate-600 hover:bg-slate-50';
        $html .= '<a href="' . htmlspecialchars(lang_switch_url($code)) . '" class="block px-4 py-2' . $active . '">' . htmlspecialchars($label) . '</a>';
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

lang_load();
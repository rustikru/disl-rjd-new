<?php

if (!function_exists('uiNotices')) {
    function uiNotices(?string $success, ?string $error): void
    {
        if ($success !== null && $success !== '') {
            echo '<div class="ui-notice ui-notice--success">' . htmlspecialchars($success) . '</div>';
        }
        if ($error !== null && $error !== '') {
            echo '<div class="ui-notice ui-notice--error">' . htmlspecialchars($error) . '</div>';
        }
    }
}

if (!function_exists('uiEditIcon')) {
    function uiEditIcon(): string
    {
        return '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 1H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1M8 1v3h3L8 1Z"/><path d="M4 5h2M4 7h1m6-1 2 2-5 5H6v-2Z"/></svg>';
    }
}

if (!function_exists('uiDeleteIcon')) {
    function uiDeleteIcon(): string
    {
        return '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h10M5 4V2h4v2m2 0-.6 8H3.6L3 4m2.5 2v4m3-4v4"/></svg>';
    }
}

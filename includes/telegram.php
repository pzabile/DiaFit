<?php
require_once __DIR__ . '/bootstrap.php';

function tg_api($method, $payload = [], $files = []) {
    $token = cfg('telegram.bot_token');
    if (!$token || strpos($token, 'REPLACE') === 0) {
        error_log('Telegram bot_token not configured.');
        return false;
    }
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init($url);

    if ($files) {
        $post = $payload;
        foreach ($files as $k => $path) {
            $post[$k] = new CURLFile($path);
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    if ($res === false) {
        error_log('Telegram curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $res;
}

function tg_send_message($text) {
    return tg_api('sendMessage', [
        'chat_id'    => cfg('telegram.chat_id'),
        'text'       => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
}

function tg_send_document($filePath, $caption = '') {
    return tg_api('sendDocument', [
        'chat_id' => cfg('telegram.chat_id'),
        'caption' => $caption,
    ], ['document' => $filePath]);
}

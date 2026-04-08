<?php

declare(strict_types=1);

$reference = '';
if (isset($_POST['orderid']) && is_string($_POST['orderid'])) {
    $reference = trim($_POST['orderid']);
} elseif (isset($_POST['RefNo']) && is_string($_POST['RefNo'])) {
    $reference = trim($_POST['RefNo']);
} elseif (isset($_GET['reference']) && is_string($_GET['reference'])) {
    $reference = trim($_GET['reference']);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$callbackUrl = sprintf('%s://%s/billing/gateways/fiuu/callback', $scheme, $host);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $handled = false;

    try {
        require __DIR__ . '/../vendor/autoload.php';
        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app->make(Pterodactyl\Services\Billing\BillingPaymentService::class)->handleFiuuCallback($_POST, true);
        $handled = true;
    } catch (Throwable $exception) {
        error_log(sprintf(
            '[fiuu-return] local callback handling failed for %s: %s in %s:%d',
            $reference !== '' ? $reference : 'unknown-reference',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
    }

    if (!$handled) {
        $postData = http_build_query($_POST, '', '&');

        if (function_exists('curl_init')) {
            $curl = curl_init($callbackUrl);
            if ($curl !== false) {
                curl_setopt_array($curl, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $postData,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Content-Length: ' . strlen($postData),
                    ],
                ]);

                if ($scheme === 'https') {
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
                }

                curl_exec($curl);
                curl_close($curl);
            }
        } else {
            @file_get_contents($callbackUrl, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Content-Length: ' . strlen($postData),
                    ]),
                    'content' => $postData,
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ]));
        }
    }
}

$target = '/billing';
if ($reference !== '') {
    $target .= '?reference=' . rawurlencode($reference);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow', true);
header('Location: ' . $target, true, 303);

echo sprintf(
    '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=%1$s"></head><body><script>window.location.replace(%2$s);</script><p>Redirecting to billing...</p><a href=%2$s>Continue</a></body></html>',
    htmlspecialchars($target, ENT_QUOTES, 'UTF-8'),
    json_encode($target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

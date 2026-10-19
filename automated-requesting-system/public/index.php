<?php
ob_start();
require_once __DIR__ . '/../vendor/autoload.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Generate a cryptographically random nonce for this request.
// Used to whitelist the single inline <style> block in base.php that injects
// Theme CSS variable. All other styling must be in external .css files.
$GLOBALS['csp_nonce'] = base64_encode(random_bytes(16));
$nonce = $GLOBALS['csp_nonce'];
$requestHost = parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST);
if (!is_string($requestHost) || !preg_match('/\A(?:[a-z0-9.-]+|\[[a-f0-9:]+\])\z/i', $requestHost)) {
	$requestHost = 'localhost';
}
$realtimeOrigin = 'https://' . $requestHost . ':8443';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: blob:; connect-src 'self' {$realtimeOrigin}; upgrade-insecure-requests;");

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../routes/web.php';
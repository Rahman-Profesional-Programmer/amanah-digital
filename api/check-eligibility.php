<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

// --- Resolve IP ---
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
if ($ip && str_contains($ip, ',')) {
    $ip = trim(explode(',', $ip)[0]);
}
if ($ip && !filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = null;
}

// 1. Cookie check (fast client-side enforcement)
if (isset($_COOKIE['anon_feedback_cooldown'])) {
    echo json_encode(['blocked' => true, 'reason' => 'Anda sudah mengirim masukan bulan ini.']);
    exit;
}

// 2. IP check in DB (30-day rolling window)
if ($ip) {
    try {
        $stmt = getPDO()->prepare(
            "SELECT COUNT(*) FROM feedback
             WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() > 0) {
            echo json_encode(['blocked' => true, 'reason' => 'Anda sudah mengirim masukan bulan ini.']);
            exit;
        }
    } catch (Throwable $e) {
        // Allow on DB error — server will re-check at submit time
    }
}

echo json_encode(['blocked' => false]);

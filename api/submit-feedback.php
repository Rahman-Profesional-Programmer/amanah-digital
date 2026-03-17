<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['message' => 'Permintaan tidak valid.']);
    exit;
}

$content = trim((string)($body['content']    ?? ''));
$rawTag  = trim((string)($body['tag_id']     ?? ''));
$rawSub  = trim((string)($body['sub_tag_id'] ?? ''));

if ($content === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Pesan tidak boleh kosong.']);
    exit;
}

// -------------------------------------------------------
// Anonymous / Bot browser detection (server-side)
// -------------------------------------------------------
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (empty($ua) || strlen($ua) < 20) {
    http_response_code(403);
    echo json_encode(['message' => 'Browser Anda tidak dapat diidentifikasi. Gunakan browser biasa.']);
    exit;
}

if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    http_response_code(403);
    echo json_encode(['message' => 'Browser anonim tidak diizinkan mengirim masukan.']);
    exit;
}

$anonPatterns = [
    '/TorBrowser/i',
    '/HeadlessChrome/i',
    '/PhantomJS/i',
    '/python-requests/i',
    '/^curl\//i',
    '/^wget\//i',
    '/Scrapy/i',
    '/Go-http-client/i',
];
foreach ($anonPatterns as $pattern) {
    if (preg_match($pattern, $ua)) {
        http_response_code(403);
        echo json_encode(['message' => 'Browser anonim atau otomatis tidak diizinkan.']);
        exit;
    }
}

// -------------------------------------------------------
// Resolve client IP
// -------------------------------------------------------
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
if ($ip && str_contains($ip, ',')) {
    $ip = trim(explode(',', $ip)[0]);
}
if ($ip && !filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = null;
}

// -------------------------------------------------------
// Rate limit: cookie check
// -------------------------------------------------------
if (isset($_COOKIE['anon_feedback_cooldown'])) {
    http_response_code(429);
    echo json_encode(['message' => 'Anda hanya dapat mengirim masukan sekali dalam sebulan.']);
    exit;
}

try {
    $pdo = getPDO();

    // -------------------------------------------------------
    // Rate limit: IP in DB (30-day window)
    // -------------------------------------------------------
    if ($ip) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM feedback
             WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() > 0) {
            http_response_code(429);
            echo json_encode(['message' => 'Anda hanya dapat mengirim masukan sekali dalam sebulan.']);
            exit;
        }
    }

    // -------------------------------------------------------
    // Resolve or create tag
    // -------------------------------------------------------
    $tagId = null;
    if ($rawTag !== '') {
        if (ctype_digit($rawTag)) {
            $s = $pdo->prepare("SELECT id FROM tags WHERE id = ?");
            $s->execute([(int)$rawTag]);
            $row = $s->fetch();
            if ($row) {
                $tagId = (int)$row['id'];
            }
        } else {
            // "New tag" typed by user
            $tagName = mb_substr($rawTag, 0, 255);
            $pdo->prepare(
                "INSERT INTO tags (name) VALUES (?)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
            )->execute([$tagName]);
            $tagId = (int)$pdo->lastInsertId();
            if ($tagId === 0) {
                $s = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                $s->execute([$tagName]);
                $tagId = (int)$s->fetchColumn() ?: null;
            }
        }
    }

    // -------------------------------------------------------
    // Resolve or create sub_tag
    // -------------------------------------------------------
    $subTagId = null;
    if ($rawSub !== '') {
        if (ctype_digit($rawSub)) {
            $s = $pdo->prepare("SELECT id FROM sub_tags WHERE id = ?");
            $s->execute([(int)$rawSub]);
            $row = $s->fetch();
            if ($row) {
                $subTagId = (int)$row['id'];
            }
        } elseif ($tagId !== null) {
            // "New sub-tag" typed by user
            $subName = mb_substr($rawSub, 0, 255);
            $pdo->prepare("INSERT INTO sub_tags (tag_id, name) VALUES (?, ?)")
                ->execute([$tagId, $subName]);
            $subTagId = (int)$pdo->lastInsertId();
        }
    }

    // -------------------------------------------------------
    // Generate UUID v4
    // -------------------------------------------------------
    $b    = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));

    // -------------------------------------------------------
    // Insert feedback
    // -------------------------------------------------------
    $pdo->prepare(
        "INSERT INTO feedback (id, content, tag_id, sub_tag_id, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$uuid, $content, $tagId, $subTagId, $ip, mb_substr($ua, 0, 500)]);

    // Update usage counters
    if ($tagId !== null) {
        $pdo->prepare("UPDATE tags SET usage_count = usage_count + 1 WHERE id = ?")
            ->execute([$tagId]);
    }
    if ($subTagId !== null) {
        $pdo->prepare("UPDATE sub_tags SET usage_count = usage_count + 1 WHERE id = ?")
            ->execute([$subTagId]);
    }

    // -------------------------------------------------------
    // Set 30-day cooldown cookie
    // -------------------------------------------------------
    setcookie('anon_feedback_cooldown', '1', [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    echo json_encode(['message' => 'Masukan berhasil dikirim. Terima kasih!']);

} catch (Throwable $e) {
    error_log('[submit-feedback] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Gagal menyimpan masukan. Silakan coba lagi.']);
}

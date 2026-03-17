<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

adminApiAuth();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

try {
    $pdo = getPDO();

    switch ($action) {

        case 'add_tag':
            $name = mb_substr(trim($body['name'] ?? ''), 0, 255);
            if ($name === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Nama topik tidak boleh kosong.']);
                exit;
            }
            $pdo->prepare("INSERT INTO tags (name) VALUES (?)")->execute([$name]);
            echo json_encode([
                'message' => "Topik \"{$name}\" berhasil ditambahkan.",
                'id'      => (int)$pdo->lastInsertId(),
                'name'    => $name,
            ]);
            break;

        case 'delete_tag':
            $id = (int)($body['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID tidak valid.']);
                exit;
            }
            $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
            echo json_encode(['message' => 'Topik berhasil dihapus.']);
            break;

        case 'add_subtag':
            $tagId = (int)($body['tag_id'] ?? 0);
            $name  = mb_substr(trim($body['name'] ?? ''), 0, 255);
            if (!$tagId || $name === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Data tidak lengkap.']);
                exit;
            }
            $pdo->prepare("INSERT INTO sub_tags (tag_id, name) VALUES (?, ?)")->execute([$tagId, $name]);
            echo json_encode([
                'message' => "Sub-topik \"{$name}\" berhasil ditambahkan.",
                'id'      => (int)$pdo->lastInsertId(),
                'name'    => $name,
                'tag_id'  => $tagId,
            ]);
            break;

        case 'delete_subtag':
            $id = (int)($body['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID tidak valid.']);
                exit;
            }
            $pdo->prepare("DELETE FROM sub_tags WHERE id = ?")->execute([$id]);
            echo json_encode(['message' => 'Sub-topik berhasil dihapus.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action tidak dikenali.']);
    }

} catch (Throwable $e) {
    error_log('[manage-tags] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan server.']);
}

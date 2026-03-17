<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo  = getPDO();
    $tags = $pdo->query("SELECT id, name FROM tags ORDER BY name ASC")->fetchAll();

    $subQ = $pdo->prepare("SELECT id, name FROM sub_tags WHERE tag_id = ? ORDER BY name ASC");
    foreach ($tags as &$tag) {
        $subQ->execute([(int)$tag['id']]);
        $tag['sub_tags'] = $subQ->fetchAll();
    }
    unset($tag);

    echo json_encode($tags, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[get-tags] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Gagal memuat data topik.']);
}

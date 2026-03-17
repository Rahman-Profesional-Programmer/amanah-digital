<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/env.php';

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name($_ENV['SESSION_NAME'] ?? 'amanah_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Redirect to login if not authenticated (for page requests). */
function requireAdminAuth(): void
{
    startSession();
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

/** Return 401 JSON if not authenticated (for API requests). */
function adminApiAuth(): void
{
    startSession();
    if (empty($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

<?php
/**
 * Notifications API endpoint.
 * Handles AJAX actions for deleting and marking notifications as read.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/includes/notifications.php';

function respondJson(int $statusCode, array $payload): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, [
        'success' => false,
        'message' => 'Method not allowed',
    ]);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    respondJson(401, [
        'success' => false,
        'message' => 'Unauthorized',
    ]);
}

$csrfFromHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfFromSession = $_SESSION['csrf_token'] ?? '';
if (!is_string($csrfFromHeader) || !is_string($csrfFromSession) || !hash_equals($csrfFromSession, $csrfFromHeader)) {
    respondJson(403, [
        'success' => false,
        'message' => 'Invalid CSRF token',
    ]);
}

$action = trim((string)($_GET['action'] ?? ''));
if ($action === '') {
    respondJson(400, [
        'success' => false,
        'message' => 'Missing action',
    ]);
}

if ($action === 'delete') {
    $notificationId = (int)($_POST['notification_id'] ?? 0);

    if ($notificationId <= 0) {
        respondJson(400, [
            'success' => false,
            'message' => 'Invalid notification_id',
        ]);
    }

    $deleted = deleteNotification($conn, $notificationId, $userId);
    if (!$deleted) {
        respondJson(500, [
            'success' => false,
            'message' => 'Failed to delete notification',
        ]);
    }

    respondJson(200, [
        'success' => true,
        'message' => 'Notification deleted',
    ]);
}

if ($action === 'mark_all_read') {
    $updated = markAllNotificationsAsRead($conn, $userId);
    if (!$updated) {
        respondJson(500, [
            'success' => false,
            'message' => 'Failed to mark notifications as read',
        ]);
    }

    respondJson(200, [
        'success' => true,
        'message' => 'Notifications marked as read',
    ]);
}

respondJson(400, [
    'success' => false,
    'message' => 'Unknown action',
]);

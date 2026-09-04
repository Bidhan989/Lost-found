<?php

session_start();

require_once __DIR__ . '/database.php';

define('BASE_URL', 'http://localhost/Lost&found/');
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/Lost&found/');

define('UPLOAD_PATH', __DIR__ . '/../Uploads/items/');
define('UPLOAD_URL', BASE_URL . 'Uploads/items/');

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url) {
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        header("Location: " . $url);
    } else {
        header("Location: " . BASE_URL . ltrim($url, '/'));
    }
    exit();
}

function clean($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

function setAlert($message, $type = 'success') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }

    return null;
}

function uploadImage($file, $itemId) {

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    $filename = $file['name'];

    $ext = strtolower(
        pathinfo($filename, PATHINFO_EXTENSION)
    );

    if (!in_array($ext, $allowed)) {
        return [
            'success' => false,
            'message' => 'Invalid file type'
        ];
    }

    if ($file['size'] > 5000000) {
        return [
            'success' => false,
            'message' => 'File too large'
        ];
    }

    $newName = uniqid() . '_' . $itemId . '.' . $ext;

    $destination = UPLOAD_PATH . $newName;

    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0777, true);
    }

    if (move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {
        return [
            'success' => true,
            'filename' => $newName
        ];
    }

    return [
        'success' => false,
        'message' => 'Upload failed'
    ];
}
/* =========================================================
   NOTIFICATION SYSTEM
========================================================= */

/**
 * Create a notification for a user.
 */
function createNotification(
    $userId,
    $title,
    $message,
    $type = 'info',
    $itemId = null,
    $claimId = null
) {

    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO notifications
        (
            user_id,
            item_id,
            claim_id,
            title,
            message,
            type
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        error_log('Notification prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param(
        "iiisss",
        $userId,
        $itemId,
        $claimId,
        $title,
        $message,
        $type
    );

    $success = $stmt->execute();

    if (!$success) {
        error_log('Notification insert failed: ' . $stmt->error);
    }

    $stmt->close();

    return $success;
}


/**
 * Get number of unread notifications.
 */
function getUnreadNotificationCount($userId) {

    global $conn;

    $userId = (int)$userId;

    $result = $conn->query("
        SELECT COUNT(*) AS count
        FROM notifications
        WHERE user_id = $userId
          AND is_read = 0
    ");

    if (!$result) {
        return 0;
    }

    return (int)$result->fetch_assoc()['count'];
}
?>
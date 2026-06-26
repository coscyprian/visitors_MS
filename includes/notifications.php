<?php
/**
 * Visitor Notification System
 * Handles creating and managing notifications for receptionists
 */

function notificationsTableHasColumn($conn, $tableName, $columnName) {
    $tableName = $conn->real_escape_string($tableName);
    $columnName = $conn->real_escape_string($columnName);
    $rs = $conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'");
    return $rs && $rs->num_rows > 0;
}

function notificationsTableHasIndex($conn, $tableName, $indexName) {
    $tableName = $conn->real_escape_string($tableName);
    $indexName = $conn->real_escape_string($indexName);
    $rs = $conn->query("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '{$indexName}'");
    return $rs && $rs->num_rows > 0;
}

function notificationsTableHasForeignKey($conn, $tableName, $constraintName) {
    $tableName = $conn->real_escape_string($tableName);
    $constraintName = $conn->real_escape_string($constraintName);
    $sql = "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableName}'
              AND CONSTRAINT_NAME = '{$constraintName}'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            LIMIT 1";
    $rs = $conn->query($sql);
    return $rs && $rs->num_rows > 0;
}

function resolveDepartmentIdFromName($conn, $department) {
    $department = trim((string)$department);
    if ($department === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT id FROM departments WHERE TRIM(LOWER(name)) = TRIM(LOWER(?)) LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $department);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)$row['id'] : null;
}

/**
 * Create notification for visitor arrival at department
 * @param mysqli $conn Database connection
 * @param int $visitor_id Visitor ID
 * @param string $department Department name
 * @param string $visitor_name Visitor's full name
 * @param int|null $department_id Department ID
 * @return bool Success status
 */
function createDepartmentNotification($conn, $visitor_id, $department, $visitor_name, $department_id = null) {
    if (empty($department)) {
        return false;
    }

    if ($department_id === null) {
        $department_id = resolveDepartmentIdFromName($conn, $department);
    }

    // Get receptionist/staff users for this department.
    if ($department_id !== null && notificationsTableHasColumn($conn, 'users', 'department_id')) {
        $stmt = $conn->prepare(" 
            SELECT id FROM users 
            WHERE (role LIKE '%receptionist%' OR role LIKE '%staff%') 
                AND department_id = ?
        ");
    } else {
        $stmt = $conn->prepare(" 
            SELECT id FROM users 
            WHERE (role LIKE '%receptionist%' OR role LIKE '%staff%') 
                AND department = ?
        ");
    }
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    if ($department_id !== null && notificationsTableHasColumn($conn, 'users', 'department_id')) {
        $stmt->bind_param("i", $department_id);
    } else {
        $stmt->bind_param("s", $department);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $created_at = date('Y-m-d H:i:s');
    $status = 'unread';
    $notification_type = 'visitor_arrival';
    $message = "Mgeni {$visitor_name} amewasili katika idara ya {$department}.";
    
    $success = true;
    
    // Create notification for each staff member
    if ($result->num_rows > 0) {
        if (notificationsTableHasColumn($conn, 'notifications', 'department_id')) {
            $insert_stmt = $conn->prepare(" 
                INSERT INTO notifications (
                    visitor_id, 
                    user_id, 
                    department,
                    department_id,
                    visitor_name, 
                    notification_type, 
                    status, 
                    created_at,
                    message
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $insert_stmt = $conn->prepare(" 
                INSERT INTO notifications (
                    visitor_id, 
                    user_id, 
                    department, 
                    visitor_name, 
                    notification_type, 
                    status, 
                    created_at,
                    message
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }

        if (!$insert_stmt) {
            error_log("Insert prepare failed: " . $conn->error);
            return false;
        }

        while ($row = $result->fetch_assoc()) {
            $user_id = $row['id'];
            if (notificationsTableHasColumn($conn, 'notifications', 'department_id')) {
                $insert_stmt->bind_param(
                    "iisisssss",
                    $visitor_id,
                    $user_id,
                    $department,
                    $department_id,
                    $visitor_name,
                    $notification_type,
                    $status,
                    $created_at,
                    $message
                );
            } else {
                $insert_stmt->bind_param(
                    "iissssss", 
                    $visitor_id, 
                    $user_id, 
                    $department, 
                    $visitor_name, 
                    $notification_type, 
                    $status, 
                    $created_at,
                    $message
                );
            }
            
            if (!$insert_stmt->execute()) {
                error_log("Notification insert failed: " . $insert_stmt->error);
                $success = false;
            }
        }
        $insert_stmt->close();
    }
    
    $stmt->close();
    return $success;
}

/**
 * Get unread notifications for current user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $limit Limit results
 * @return array Array of notifications
 */
function getUnreadNotifications($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT n.*, v.full_name, v.phone_number, v.check_in_time
        FROM notifications n
        LEFT JOIN visitors v ON n.visitor_id = v.id
        WHERE n.user_id = ? AND n.status = 'unread'
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }

    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    return $notifications;
}

/**
 * Get all notifications for current user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $limit Limit results
 * @return array Array of notifications
 */
function getAllNotifications($conn, $user_id, $limit = 20) {
    $stmt = $conn->prepare("
        SELECT n.*, v.full_name, v.phone_number, v.check_in_time
        FROM notifications n
        LEFT JOIN visitors v ON n.visitor_id = v.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }

    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    return $notifications;
}

/**
 * Mark notification as read
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @return bool Success status
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    $status = 'read';
    $stmt = $conn->prepare("UPDATE notifications SET status = ? WHERE id = ? AND user_id = ?");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("sii", $status, $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Mark all notifications as read for user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool Success status
 */
function markAllNotificationsAsRead($conn, $user_id) {
    $status = 'read';
    $stmt = $conn->prepare("UPDATE notifications SET status = ? WHERE user_id = ? AND status = 'unread'");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("si", $status, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get unread notification count for user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return int Count of unread notifications
 */
function getUnreadNotificationCount($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND status = 'unread'
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return 0;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)$result['count'];
}

/**
 * Delete notification
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @return bool Success status
 */
function deleteNotification($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Ensure notifications table exists
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function ensureNotificationsTableExists($conn) {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");

    if (!$result || $result->num_rows === 0) {
        // Create table if it doesn't exist
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                visitor_id INT NOT NULL,
                user_id INT NOT NULL,
                department VARCHAR(255),
                department_id INT NULL,
                visitor_name VARCHAR(255),
                notification_type VARCHAR(50) DEFAULT 'visitor_arrival',
                status VARCHAR(20) DEFAULT 'unread',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                read_at TIMESTAMP NULL,
                message TEXT,
                FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_status (user_id, status),
                INDEX idx_created_at (created_at),
                INDEX idx_notifications_department_id (department_id)
            )
        ";

        if (!$conn->query($create_table_sql)) {
            error_log("Failed to create notifications table: " . $conn->error);
            return false;
        }
    }

    if (!notificationsTableHasColumn($conn, 'notifications', 'department_id')) {
        $conn->query("ALTER TABLE notifications ADD COLUMN department_id INT NULL");
    }

    if (notificationsTableHasColumn($conn, 'notifications', 'department') && notificationsTableHasColumn($conn, 'notifications', 'department_id')) {
        $conn->query("UPDATE notifications n
            JOIN departments d ON TRIM(LOWER(n.department)) = TRIM(LOWER(d.name))
            SET n.department_id = d.id
            WHERE n.department_id IS NULL
              AND TRIM(COALESCE(n.department, '')) <> ''");

        $conn->query("UPDATE notifications n
            JOIN departments d ON n.department_id = d.id
            SET n.department = d.name
            WHERE TRIM(COALESCE(n.department, '')) = ''");

        $conn->query("UPDATE notifications n
            LEFT JOIN departments d ON n.department_id = d.id
            SET n.department_id = NULL
            WHERE n.department_id IS NOT NULL AND d.id IS NULL");
    }

    if (!notificationsTableHasIndex($conn, 'notifications', 'idx_notifications_department_id')) {
        $conn->query("ALTER TABLE notifications ADD INDEX idx_notifications_department_id (department_id)");
    }

    if (!notificationsTableHasForeignKey($conn, 'notifications', 'fk_notifications_department_id')) {
        $conn->query("ALTER TABLE notifications
            ADD CONSTRAINT fk_notifications_department_id
            FOREIGN KEY (department_id) REFERENCES departments(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE");
    }

    return true;
}

?>

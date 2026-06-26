<?php
/**
 * Department management helpers
 */

function tableHasColumn($conn, $tableName, $columnName) {
    $tableName = $conn->real_escape_string($tableName);
    $columnName = $conn->real_escape_string($columnName);
    $rs = $conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'");
    return $rs && $rs->num_rows > 0;
}

function tableExists($conn, $tableName) {
    $tableName = $conn->real_escape_string($tableName);
    $rs = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    return $rs && $rs->num_rows > 0;
}

function tableHasIndex($conn, $tableName, $indexName) {
    $tableName = $conn->real_escape_string($tableName);
    $indexName = $conn->real_escape_string($indexName);
    $rs = $conn->query("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '{$indexName}'");
    return $rs && $rs->num_rows > 0;
}

function tableHasForeignKey($conn, $tableName, $constraintName) {
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

function ensureInnoDBEngine($conn, $tableName) {
    $tableName = $conn->real_escape_string($tableName);
    $sql = "SELECT ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableName}'
            LIMIT 1";
    $rs = $conn->query($sql);
    $row = $rs ? $rs->fetch_assoc() : null;
    $engine = strtoupper((string)($row['ENGINE'] ?? ''));

    if ($engine !== '' && $engine !== 'INNODB') {
        $conn->query("ALTER TABLE `{$tableName}` ENGINE=InnoDB");
    }
}

function getDepartmentIdByName($conn, $departmentName) {
    $departmentName = trim((string)$departmentName);
    if ($departmentName === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT id FROM departments WHERE TRIM(LOWER(name)) = TRIM(LOWER(?)) LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $departmentName);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)$row['id'] : null;
}

function ensureTableDepartmentLink($conn, $tableName, $departmentTextColumn, $fkName, $indexName) {
    if (!tableExists($conn, $tableName)) {
        return;
    }

    ensureInnoDBEngine($conn, 'departments');
    ensureInnoDBEngine($conn, $tableName);

    if (!tableHasColumn($conn, $tableName, 'department_id')) {
        $conn->query("ALTER TABLE `{$tableName}` ADD COLUMN department_id INT NULL");
    }

    if (!tableHasColumn($conn, $tableName, $departmentTextColumn) || !tableHasColumn($conn, $tableName, 'department_id')) {
        return;
    }

    $conn->query("UPDATE `{$tableName}` t
        JOIN departments d ON TRIM(LOWER(t.`{$departmentTextColumn}`)) = TRIM(LOWER(d.name))
        SET t.department_id = d.id
        WHERE t.department_id IS NULL
          AND TRIM(COALESCE(t.`{$departmentTextColumn}`, '')) <> ''");

    $conn->query("UPDATE `{$tableName}` t
        JOIN departments d ON t.department_id = d.id
        SET t.`{$departmentTextColumn}` = d.name
        WHERE TRIM(COALESCE(t.`{$departmentTextColumn}`, '')) = ''");

    $conn->query("UPDATE `{$tableName}` t
        LEFT JOIN departments d ON t.department_id = d.id
        SET t.department_id = NULL
        WHERE t.department_id IS NOT NULL AND d.id IS NULL");

    if (!tableHasIndex($conn, $tableName, $indexName)) {
        $conn->query("ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` (department_id)");
    }

    if (!tableHasForeignKey($conn, $tableName, $fkName)) {
        $conn->query("ALTER TABLE `{$tableName}`
            ADD CONSTRAINT `{$fkName}`
            FOREIGN KEY (department_id) REFERENCES departments(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE");
    }
}

function syncDepartmentReferences($conn) {
    ensureTableDepartmentLink($conn, 'users', 'department', 'fk_users_department_id', 'idx_users_department_id');
    ensureTableDepartmentLink($conn, 'visitors', 'department', 'fk_visitors_department_id', 'idx_visitors_department_id');
    ensureTableDepartmentLink($conn, 'notifications', 'department', 'fk_notifications_department_id', 'idx_notifications_department_id');
    ensureTableDepartmentLink($conn, 'hosts', 'department', 'fk_hosts_department_id', 'idx_hosts_department_id');
}

function ensureDepartmentsTableExists($conn) {
    $createSql = "
        CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            code VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_department_name (name),
            UNIQUE KEY uniq_department_code (code)
        ) ENGINE=InnoDB
    ";

    if (!$conn->query($createSql)) {
        error_log('Failed creating departments table: ' . $conn->error);
        return false;
    }

    // Backward compatibility: migrate old departments schema if it exists.
    if (!tableHasColumn($conn, 'departments', 'name')) {
        if (!$conn->query("ALTER TABLE departments ADD COLUMN name VARCHAR(150) NULL")) {
            error_log('Failed adding name column: ' . $conn->error);
        } else {
            if (tableHasColumn($conn, 'departments', 'department_name')) {
                $conn->query("UPDATE departments SET name = department_name WHERE (name IS NULL OR name = '')");
            } elseif (tableHasColumn($conn, 'departments', 'department')) {
                $conn->query("UPDATE departments SET name = department WHERE (name IS NULL OR name = '')");
            }
            $conn->query("UPDATE departments SET name = CONCAT('Department ', id) WHERE (name IS NULL OR name = '')");
        }
    }

    if (!tableHasColumn($conn, 'departments', 'code')) {
        if (!$conn->query("ALTER TABLE departments ADD COLUMN code VARCHAR(30) NULL")) {
            error_log('Failed adding code column: ' . $conn->error);
        } else {
            if (tableHasColumn($conn, 'departments', 'department_code')) {
                $conn->query("UPDATE departments SET code = department_code WHERE (code IS NULL OR code = '')");
            }
            $conn->query("UPDATE departments SET code = UPPER(LEFT(REPLACE(name, ' ', ''), 6)) WHERE (code IS NULL OR code = '')");
            $conn->query("UPDATE departments SET code = CONCAT('DPT', id) WHERE (code IS NULL OR code = '')");
        }
    }

    if (!tableHasColumn($conn, 'departments', 'status')) {
        if (!$conn->query("ALTER TABLE departments ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'")) {
            error_log('Failed adding status column: ' . $conn->error);
        } else {
            if (tableHasColumn($conn, 'departments', 'is_active')) {
                $conn->query("UPDATE departments SET status = CASE WHEN is_active = 0 THEN 'Inactive' ELSE 'Active' END");
            }
        }
    }

    if (!tableHasColumn($conn, 'departments', 'created_at')) {
        $conn->query("ALTER TABLE departments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

    if (!tableHasColumn($conn, 'departments', 'updated_at')) {
        $conn->query("ALTER TABLE departments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    $conn->query("UPDATE departments SET status = 'Active' WHERE status IS NULL OR status = ''");

    $countRs = $conn->query("SELECT COUNT(*) AS c FROM departments");
    if (!$countRs) {
        syncDepartmentReferences($conn);
        return true;
    }

    $countRow = $countRs->fetch_assoc();
    if ((int)($countRow['c'] ?? 0) > 0) {
        syncDepartmentReferences($conn);
        return true;
    }

    $seedData = [
        ['Accounts', 'ACC', 'Active'],
        ['Administration', 'ADM', 'Active'],
        ['Finance', 'FIN', 'Inactive'],
        ['Human Resource', 'HR', 'Inactive'],
        ['ICT', 'ICT', 'Active'],
        ['Legal', 'LEGAL', 'Active'],
        ['Operations', 'OPS', 'Active'],
        ['Procurement', 'PROC', 'Active'],
        ['quarter master', 'QRT', 'Active'],
        ['Security', 'SEC', 'Active'],
        ['Transport', 'TRANS', 'Active'],
    ];

    $insertStmt = $conn->prepare("INSERT INTO departments (name, code, status) VALUES (?, ?, ?)");
    if (!$insertStmt) {
        return true;
    }

    foreach ($seedData as $row) {
        $name = $row[0];
        $code = $row[1];
        $status = $row[2];
        $insertStmt->bind_param('sss', $name, $code, $status);
        $insertStmt->execute();
    }

    $insertStmt->close();
    syncDepartmentReferences($conn);
    return true;
}

function getDepartments($conn, $onlyActive = false) {
    $departments = [];
    $sql = "SELECT id, name, code, status FROM departments";
    if ($onlyActive) {
        $sql .= " WHERE status = 'Active'";
    }
    $sql .= " ORDER BY name ASC";

    $rs = $conn->query($sql);
    if ($rs) {
        while ($row = $rs->fetch_assoc()) {
            $departments[] = $row;
        }
    }

    return $departments;
}

function addDepartment($conn, $name, $code, $status) {
    $name = trim($name);
    $code = strtoupper(trim($code));
    $status = $status === 'Inactive' ? 'Inactive' : 'Active';

    $stmt = $conn->prepare("INSERT INTO departments (name, code, status) VALUES (?, ?, ?)");
    if (!$stmt) {
        return [false, 'Imeshindikana kuandaa taarifa ya kuongeza idara.'];
    }

    $stmt->bind_param('sss', $name, $code, $status);
    if ($stmt->execute()) {
        $stmt->close();
        return [true, 'Idara imeongezwa kwa mafanikio.'];
    }

    $msg = 'Tatizo wakati wa kuongeza idara: ' . $stmt->error;
    $stmt->close();
    return [false, $msg];
}

function updateDepartment($conn, $id, $name, $code, $status) {
    $name = trim($name);
    $code = strtoupper(trim($code));
    $status = $status === 'Inactive' ? 'Inactive' : 'Active';

    $stmt = $conn->prepare("UPDATE departments SET name = ?, code = ?, status = ? WHERE id = ?");
    if (!$stmt) {
        return [false, 'Imeshindikana kuandaa taarifa ya kuhariri idara.'];
    }

    $stmt->bind_param('sssi', $name, $code, $status, $id);
    if ($stmt->execute()) {
        $stmt->close();
        return [true, 'Idara imehaririwa kwa mafanikio.'];
    }

    $msg = 'Tatizo wakati wa kuhariri idara: ' . $stmt->error;
    $stmt->close();
    return [false, $msg];
}

function deleteDepartment($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
    if (!$stmt) {
        return [false, 'Imeshindikana kuandaa taarifa ya kufuta idara.'];
    }

    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $stmt->close();
        return [true, 'Idara imefutwa kwa mafanikio.'];
    }

    $msg = 'Tatizo wakati wa kufuta idara: ' . $stmt->error;
    $stmt->close();
    return [false, $msg];
}

function getDepartmentById($conn, $id) {
    $stmt = $conn->prepare("SELECT id, name, code, status FROM departments WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

?>
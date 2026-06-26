<?php

function normalizeUserRole($role) {
    $value = strtolower(trim((string) $role));

    if (in_array($value, ['admin', 'administrator'], true)) {
        return 'Admin';
    }

    if (in_array($value, ['security', 'gate security', 'gate_security', 'gate-security'], true)) {
        return 'Security';
    }

    if (in_array($value, ['receptionist', 'reception', 'staff'], true)) {
        return 'Receptionist';
    }

    return $value !== '' ? ucfirst($value) : 'Receptionist';
}

function normalizedRoleKey($role) {
    return strtolower(normalizeUserRole($role));
}

function isAdminRole($role) {
    return normalizedRoleKey($role) === 'admin';
}

function isReceptionistRole($role) {
    return normalizedRoleKey($role) === 'receptionist';
}

function isSecurityRole($role) {
    return normalizedRoleKey($role) === 'security';
}

function getRoleLabel($role) {
    return normalizeUserRole($role);
}

function getRoleHomePage($role) {
    if (isAdminRole($role)) {
        return 'dashboard.php';
    }

    if (isSecurityRole($role)) {
        return 'gate_security_dashboard.php';
    }

    return 'department_dashboard.php';
}

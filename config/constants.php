<?php

declare(strict_types=1);

/**
 * Project-wide constants.
 * These constants are optional helpers and do not replace config.php.
 */

if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 'admin');
}

if (!defined('ROLE_STUDENT')) {
    define('ROLE_STUDENT', 'student');
}

if (!defined('STATUS_ACTIVE')) {
    define('STATUS_ACTIVE', 'active');
}

if (!defined('STATUS_CANCELLED')) {
    define('STATUS_CANCELLED', 'cancelled');
}

if (!defined('STATUS_COMPLETED')) {
    define('STATUS_COMPLETED', 'completed');
}

if (!defined('STATION_ACTIVE')) {
    define('STATION_ACTIVE', 'active');
}

if (!defined('STATION_MAINTENANCE')) {
    define('STATION_MAINTENANCE', 'maintenance');
}

if (!defined('STATION_PASSIVE')) {
    define('STATION_PASSIVE', 'passive');
}

if (!defined('EQUIPMENT_AVAILABLE')) {
    define('EQUIPMENT_AVAILABLE', 'available');
}

if (!defined('EQUIPMENT_MAINTENANCE')) {
    define('EQUIPMENT_MAINTENANCE', 'maintenance');
}

if (!defined('EQUIPMENT_PASSIVE')) {
    define('EQUIPMENT_PASSIVE', 'passive');
}
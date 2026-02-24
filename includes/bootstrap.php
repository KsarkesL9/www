<?php
/**
 * Centralny autoloader — ładuje wszystkie moduły backendu.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/password.php';

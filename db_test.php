<?php

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::connect();
    echo 'OK';
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage();
}

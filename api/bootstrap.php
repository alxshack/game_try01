<?php
spl_autoload_register(function ($class_name) {
    include __DIR__ . '/../classes/' . $class_name . '.php';
});

// Загружаем классы до session_start(), чтобы unserialize работал корректно
class_exists('GameState');
class_exists('Map');
class_exists('Entity');
class_exists('Pathfinder');
class_exists('CombatResolver');
class_exists('Logger');

session_start();

header('Content-Type: application/json');

function sendResponse($data) {
    echo json_encode($data);
    session_write_close();
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    session_write_close();
    exit;
}

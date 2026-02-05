<?php
session_start();

spl_autoload_register(function ($class_name) {
    include __DIR__ . '/../classes/' . $class_name . '.php';
});

header('Content-Type: application/json');

function sendResponse($data) {
    echo json_encode($data);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

<?php
require_once 'bootstrap.php';

$filename = $_GET['file'] ?? '';
if (!$filename) {
    sendError('Filename is required');
}

// Security check: prevent directory traversal
$filename = basename($filename);
$path = __DIR__ . '/../data/maps/' . $filename;

if (!file_exists($path)) {
    sendError('File not found');
}

$content = file_get_contents($path);
$data = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendError('Invalid JSON in map file');
}

sendResponse($data);

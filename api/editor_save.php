<?php
require_once 'bootstrap.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['filename'])) {
    sendError('Invalid request data');
}

$filename = basename($data['filename']);
$path = __DIR__ . '/../data/maps/' . $filename;

// Prepare data for saving (remove filename from the content)
$mapData = $data['map'];

// Basic validation of map structure
if (!isset($mapData['width'], $mapData['height'], $mapData['levels'], $mapData['tiles'], $mapData['entities'])) {
    sendError('Invalid map structure');
}

$encoded = json_encode($mapData, JSON_PRETTY_PRINT);
if ($encoded === false) {
    sendError('JSON encoding failed: ' . json_last_error_msg());
}
$success = @file_put_contents($path, $encoded);

if ($success === false) {
    $error = error_get_last();
    sendError('Failed to save map file: ' . ($error['message'] ?? 'Unknown error'));
}

sendResponse(['success' => true]);

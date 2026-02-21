<?php
require_once 'bootstrap.php';

$mapsDir = __DIR__ . '/../data/maps';
if (!is_dir($mapsDir)) {
    sendResponse(['maps' => []]);
}

$files = scandir($mapsDir);
$maps = [];
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
        $maps[] = $file;
    }
}

sort($maps);
sendResponse(['maps' => $maps]);

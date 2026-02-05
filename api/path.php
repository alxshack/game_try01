<?php
require_once 'bootstrap.php';

$state = GameState::load();
if (!$state) {
    sendError('No active game session');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['x']) || !isset($input['y']) || !isset($input['z'])) {
    sendError('Missing target coordinates');
}

$start = $state->hero->position;
$end = ['x' => $input['x'], 'y' => $input['y'], 'z' => $input['z']];

$path = Pathfinder::findPath($state->map, $start, $end, $state->enemies);

if ($path === null) {
    sendError('No path found');
}

sendResponse(['path' => $path]);

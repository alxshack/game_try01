<?php
require_once 'bootstrap.php';

$state = GameState::load();
if (!$state) {
    sendError('No active game session');
}

if ($state->gameOver) {
    sendError('Game over');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['path']) || !is_array($input['path'])) {
    sendError('Missing or invalid path');
}

$fullPath = $input['path'];
$movedPath = [];
$combatTriggered = false;
$enemyEncountered = null;

// The first element of path is usually the current position, skip it
if (count($fullPath) > 0 && 
    $fullPath[0]['x'] === $state->hero->position['x'] && 
    $fullPath[0]['y'] === $state->hero->position['y'] && 
    $fullPath[0]['z'] === $state->hero->position['z']) {
    array_shift($fullPath);
}

foreach ($fullPath as $step) {
    // Check if next tile has an enemy
    $enemy = $state->getEnemyAt($step['x'], $step['y'], $step['z']);
    if ($enemy) {
        $combatTriggered = true;
        $enemyEncountered = $enemy;
        // Move hero TO the enemy tile to trigger combat
        $state->hero->position = $step;
        $movedPath[] = $step;
        break;
    }

    // Move hero
    $state->hero->position = $step;
    $movedPath[] = $step;

    // Check for exit
    $tile = $state->map->getTile($step['x'], $step['y'], $step['z']);
    if ($tile && $tile['type'] === 'exit') {
        $state->victory = true;
        $state->gameOver = true;
        $state->addLog("You reached the exit! Victory!");
        break;
    }
}

GameState::save($state);

sendResponse([
    'state' => $state->toArray(),
    'movedPath' => $movedPath,
    'combatTriggered' => $combatTriggered,
    'enemy' => $combatTriggered ? $enemyEncountered->toArray() : null
]);

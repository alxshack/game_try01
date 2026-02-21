<?php

require_once 'bootstrap.php';

Logger::info('path', 'Path calculation started');
Logger::info('path', 'Session ID: ' . session_id());
Logger::info('path', 'Session data exists: ' . (isset($_SESSION['game_state']) ? 'YES' : 'NO'));

$state = GameState::load();
if (!$state) {
    Logger::error('path', 'GameState::load() returned null');
    sendError('No active game session');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['x']) || !isset($input['y']) || !isset($input['z'])) {
    sendError('Missing target coordinates');
}

$start = $state->hero->position;
$end = [
    'x' => (int)$input['x'],
    'y' => (int)$input['y'],
    'z' => (int)$input['z']
];

// Получаем видимые плитки (туман войны)
$visibleTiles = $input['visibleTiles'] ?? [];

// Создаём множество видимых плиток для быстрой проверки
$visibleSet = [];
foreach ($visibleTiles as $tile) {
    $key = "{$tile['x']},{$tile['y']},{$tile['z']}";
    $visibleSet[$key] = true;
}

// Передаём ВСЕХ врагов (враг блокирует путь, даже если не виден)
$path = Pathfinder::findPath($state->map, $start, $end, $state->enemies, $visibleTiles);

    if ($path === null) {
        Logger::error('path', sprintf(
            "Path not found: hero at (%d,%d,%d), target at (%d,%d,%d), map: %s, level: %d",
            $start['x'], $start['y'], $start['z'],
            $end['x'], $end['y'], $end['z'],
            $state->mapPath, $state->currentLevel
        ));
        sendError('No path found');
    }

sendResponse(['path' => $path]);

<?php
require_once 'bootstrap.php';

try {
    Logger::info('state', 'State request started');
    Logger::info('state', 'Session ID: ' . session_id());

    $state = GameState::load();

    if (!$state) {
        Logger::info('state', 'No existing game state, creating new');
        // Initialize new game if no state exists
        $mapFile = Map::getFirstMapPath();
        if (!$mapFile || !file_exists($mapFile)) {
            sendError('No maps found in data/maps folder');
        }
        $map = Map::loadFromJson($mapFile);
        $state = new GameState($map, $mapFile);
        GameState::save($state);
        Logger::info('state', 'New game state saved, session data exists: ' . (isset($_SESSION['game_state']) ? 'YES' : 'NO'));
    }

    sendResponse($state->toArray());
} catch (Throwable $e) {
    sendError('Server Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500);
}

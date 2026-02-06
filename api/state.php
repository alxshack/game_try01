<?php
require_once 'bootstrap.php';

$state = GameState::load();

if (!$state) {
    // Initialize new game if no state exists
    $mapFile = Map::getFirstMapPath();
    if (!$mapFile || !file_exists($mapFile)) {
        sendError('No maps found in data/maps folder');
    }
    $map = Map::loadFromJson($mapFile);
    $state = new GameState($map);
    GameState::save($state);
}

sendResponse($state->toArray());

<?php
require_once 'bootstrap.php';

$mapFile = Map::getFirstMapPath();
if (!$mapFile || !file_exists($mapFile)) {
    sendError('No maps found in data/maps folder');
}

$map = Map::loadFromJson($mapFile);
$state = new GameState($map, $mapFile);

// Проверяем условие проигрыша в самом начале (на всякий случай)
$state->checkLossCondition();

GameState::save($state);

sendResponse($state->toArray());

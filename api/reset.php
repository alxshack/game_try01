<?php
require_once 'bootstrap.php';

$mapFile = __DIR__ . '/../data/maps/map1.json';
if (!file_exists($mapFile)) {
    $script = __DIR__ . "/../scripts/generate_map.php";
    exec("php $script map1.json");
}
$map = Map::loadFromJson($mapFile);
$state = new GameState($map);
GameState::save($state);

sendResponse($state->toArray());

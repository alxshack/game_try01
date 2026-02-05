<?php
require_once 'bootstrap.php';

$map = Map::loadFromJson(__DIR__ . '/../data/maps/map1.json');
$state = new GameState($map);
GameState::save($state);

sendResponse($state->toArray());

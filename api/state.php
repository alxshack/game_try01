<?php
require_once 'bootstrap.php';

$state = GameState::load();

if (!$state) {
    // Initialize new game if no state exists
    $map = Map::loadFromJson(__DIR__ . '/../data/maps/map1.json');
    $state = new GameState($map);
    GameState::save($state);
}

sendResponse($state->toArray());

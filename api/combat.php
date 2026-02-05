<?php
require_once 'bootstrap.php';

$state = GameState::load();
if (!$state) {
    sendError('No active game session');
}

if ($state->gameOver) {
    sendError('Game over');
}

$enemy = $state->getEnemyAt($state->hero->position['x'], $state->hero->position['y'], $state->hero->position['z']);
if (!$enemy) {
    sendError('No enemy at current position');
}

$resolution = CombatResolver::resolve($state->hero, $enemy);

if ($resolution['result'] === 'victory') {
    $state->hero->hp += $enemy->hp;
    $state->removeEnemy($enemy);
    $state->addLog("Defeated {$enemy->type}! Gained {$enemy->hp} HP. Current HP: {$state->hero->hp}");
} else {
    $state->gameOver = true;
    $state->addLog("Defeated by {$enemy->type}... Game Over.");
}

GameState::save($state);

sendResponse([
    'state' => $state->toArray(),
    'resolution' => $resolution
]);

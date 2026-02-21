<?php
require_once 'bootstrap.php';

$state = GameState::load();
if (!$state) {
    sendError('No active game session');
}

if ($state->gameOver) {
    sendError('Game over');
}

// Определяем врага: при новой логике боя герой стоит на соседней клетке,
// поэтому используем координаты, сохранённые в состоянии, если они есть
$enemyPos = $state->pendingCombatEnemyPos ?? null;
$enemy = null;
if ($enemyPos) {
    $enemy = $state->getEnemyAt($enemyPos['x'], $enemyPos['y'], $enemyPos['z']);
}
if (!$enemy) {
    // Fallback для совместимости со старой логикой
    $enemy = $state->getEnemyAt($state->hero->position['x'], $state->hero->position['y'], $state->hero->position['z']);
}
if (!$enemy) {
    sendError('No enemy to fight');
}

$resolution = CombatResolver::resolve($state->hero, $enemy);
$heroHPBefore = $state->hero->hp;
$enemyHP = $enemy->hp;

if ($resolution['result'] === 'victory') {
    // Нелинейное начисление HP в зависимости от текущего HP героя
    $gain = 0;
    if ($heroHPBefore <= 100) {
        $gain = (int)floor($enemyHP * 0.5);
    } elseif ($heroHPBefore <= 200) {
        $gain = (int)floor($enemyHP * 0.4);
    } elseif ($heroHPBefore <= 300) {
        $gain = (int)floor($enemyHP * 0.3);
    } elseif ($heroHPBefore <= 500) {
        $gain = (int)floor($enemyHP * 0.2);
    } else {
        $gain = (int)floor($enemyHP * 0.1);
    }

    $hpChange = max(1, $gain);
    $state->hero->hp = $heroHPBefore + $hpChange;

    // Победив, занимаем клетку противника
    $state->hero->position = [
        'x' => $enemy->position['x'],
        'y' => $enemy->position['y'],
        'z' => $enemy->position['z']
    ];

    $state->removeEnemy($enemy);
    $state->addLog("Defeated {$enemy->type}! Gained +$gain HP. Current HP: {$state->hero->hp}");

    // Бой завершён
    $state->pendingCombatEnemyPos = null;
} else {
    // Поражение не завершает игру: снимаем 50%..90% HP героя до боя
    $diff = max(0, $enemyHP - $heroHPBefore);
    $ratio = ($heroHPBefore > 0) ? min(1.0, $diff / $heroHPBefore) : 1.0; // 0..1
    $penaltyFrac = 0.5 + 0.4 * $ratio; // 0.5..0.9
    $penalty = (int)floor($heroHPBefore * $penaltyFrac);

    $state->hero->hp = max(1, $heroHPBefore - $penalty);
    $hpChange = -($heroHPBefore - $state->hero->hp);
    $state->addLog("Defeated by {$enemy->type}. Lost " . abs($hpChange) . " HP (penalty " . round($penaltyFrac*100) . "%). Current HP: {$state->hero->hp}");

    // Бой завершён, противник остаётся на месте
    $state->pendingCombatEnemyPos = null;
}

// Проверяем условие проигрыша после боя
$state->checkLossCondition();

GameState::save($state);

sendResponse([
    'state' => $state->toArray(),
    'resolution' => $resolution,
    'hp_change' => $hpChange
]);

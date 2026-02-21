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
// очистим возможный «хвост» незавершенного боя
$state->pendingCombatEnemyPos = null;

// The first element of path is usually the current position, skip it
if (count($fullPath) > 0 && 
    $fullPath[0]['x'] === $state->hero->position['x'] && 
    $fullPath[0]['y'] === $state->hero->position['y'] && 
    $fullPath[0]['z'] === $state->hero->position['z']) {
    array_shift($fullPath);
}

foreach ($fullPath as $step) {
    // Проверяем: следующая клетка содержит врага?
    $enemy = $state->getEnemyAt($step['x'], $step['y'], $step['z']);
    if ($enemy) {
        $combatTriggered = true;
        $enemyEncountered = $enemy;
        // НЕ занимаем клетку врага: останавливаемся на текущей (последней пройденной) клетке
        // и запоминаем координаты врага для боя
        $state->pendingCombatEnemyPos = [
            'x' => $step['x'],
            'y' => $step['y'],
            'z' => $step['z']
        ];
        // В movedPath не добавляем клетку врага
        break;
    }

    // Двигаем героя на следующую клетку пути
    $state->hero->position = $step;
    $movedPath[] = $step;

    // Проверяем выход
    $tile = $state->map->getTile($step['x'], $step['y'], $step['z']);
    if ($tile && $tile['type'] === 'exit') {
        $currentLvl = (int)($state->currentLevel ?? 1);
        $nextLevel = $currentLvl + 1;
        
        $nextMapFile = Map::getMapPath($nextLevel);

        if ($nextMapFile && file_exists($nextMapFile)) {
            $oldHeroHP = $state->hero->hp;
            try {
                $newMap = Map::loadFromJson($nextMapFile);
                $state = new GameState($newMap, $nextMapFile, $nextLevel);
                $state->hero->hp = $oldHeroHP;
                $state->addLog("You reached the exit! Welcome to Level $nextLevel!");
                // Проверяем возможность продолжить игру на новом уровне
                $state->checkLossCondition();
            } catch (Exception $e) {
                $state->addLog("Error loading next map: " . $e->getMessage());
                $state->victory = true;
                $state->gameOver = true;
            }
        } else {
            $state->victory = true;
            $state->gameOver = true;
            $state->addLog("You reached the exit! Victory!");
        }
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

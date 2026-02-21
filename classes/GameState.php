<?php

class GameState {
    public Entity $hero;
    public array $enemies;
    public Map $map;
    public ?string $mapPath = null;
    public array $log;
    public bool $gameOver;
    public bool $victory;
    public int $currentLevel;
    public ?array $pendingCombatEnemyPos = null; // ['x'=>int,'y'=>int,'z'=>int]

    public function __construct(Map $map, string $mapPath, int $level = 1) {
        $this->map = $map;
        $this->mapPath = $mapPath;
        $this->currentLevel = $level;
        if ($level === 1) {
            $this->log = ["Game started! Welcome to HP Accumulator."];
        } else {
            $this->log = ["Welcome to Level $level!"];
        }
        $this->gameOver = false;
        $this->victory = false;
        
        $this->enemies = [];
        foreach ($map->entities as $entity) {
            if ($entity->type === 'hero') {
                $this->hero = $entity;
            } else {
                $this->enemies[] = $entity;
            }
        }
    }

    public function addLog(string $message): void {
        array_unshift($this->log, $message);
        if (count($this->log) > 10) {
            array_pop($this->log);
        }
    }

    public static function save(GameState $state): void {
        $_SESSION['game_state'] = serialize($state);
    }

    public static function load(): ?GameState {
        if (isset($_SESSION['game_state'])) {
            try {
                $state = unserialize($_SESSION['game_state']);
                if ($state instanceof GameState) {
                    // Re-load map object if we have the path but not the object
                    // In PHP 7.4+ typed properties can be uninitialized. isset() returns false for them.
                    if ((!isset($state->map) || $state->map === null) && $state->mapPath && file_exists($state->mapPath)) {
                        $state->map = Map::loadFromJson($state->mapPath);
                    }
                    return $state;
                }
            } catch (Throwable $e) {
                Logger::error('system', "Session unserialize failed: " . $e->getMessage());
                unset($_SESSION['game_state']);
            }
        }
        return null;
    }

    public function __sleep(): array {
        // Exclude 'map' from serialization to keep session small
        return ['hero', 'enemies', 'log', 'gameOver', 'victory', 'currentLevel', 'pendingCombatEnemyPos', 'mapPath'];
    }

    public function getEnemyAt(int $x, int $y, int $z): ?Entity {
        foreach ($this->enemies as $enemy) {
            if ($enemy->position['x'] === $x && $enemy->position['y'] === $y && $enemy->position['z'] === $z) {
                return $enemy;
            }
        }
        return null;
    }

    public function removeEnemy(Entity $enemy): void {
        $key = array_search($enemy, $this->enemies, true);
        if ($key !== false) {
            unset($this->enemies[$key]);
            $this->enemies = array_values($this->enemies);
        }
    }

    /**
     * Проверяет, не проиграл ли игрок по причине невозможности победить оставшихся врагов.
     */
    public function checkLossCondition(): void {
        if ($this->gameOver || $this->victory) {
            return;
        }

        if (empty($this->enemies)) {
            // Если врагов нет, это может быть победа на уровне, но не проигрыш по HP.
            return;
        }

        $canWinAny = false;
        foreach ($this->enemies as $enemy) {
            // Игрок может победить, если его HP больше или равно HP врага (шанс 50/50 при равенстве)
            if ($this->hero->hp >= $enemy->hp) {
                // Проверяем, можно ли дойти до этого врага
                $path = Pathfinder::findPath($this->map, $this->hero->position, $enemy->position, $this->enemies);
                if ($path !== null) {
                    $canWinAny = true;
                    break;
                }
            }
        }

        if (!$canWinAny) {
            $this->gameOver = true;
            $this->addLog("Game Over: No reachable enemies you can defeat! (Your HP: {$this->hero->hp})");
        }
    }

    public function toArray(): array {
        return [
            'hero' => $this->hero->toArray(),
            'enemies' => array_map(fn($e) => $e->toArray(), $this->enemies),
            'log' => $this->log,
            'gameOver' => $this->gameOver,
            'victory' => $this->victory,
            'currentLevel' => $this->currentLevel,
            'map' => [
                'width' => $this->map->width,
                'height' => $this->map->height,
                'levels' => $this->map->levels,
                'tiles' => $this->map->tiles // Simplified for now
            ]
        ];
    }
}

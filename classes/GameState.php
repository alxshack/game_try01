<?php

class GameState {
    public Entity $hero;
    public array $enemies;
    public Map $map;
    public array $log;
    public bool $gameOver;
    public bool $victory;
    public int $currentLevel;

    public function __construct(Map $map, int $level = 1) {
        $this->map = $map;
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
            return unserialize($_SESSION['game_state']);
        }
        return null;
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

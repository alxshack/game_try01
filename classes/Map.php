<?php

class Map {
    public int $width;
    public int $height;
    public int $levels;
    public array $tiles; // 3D array [z][y][x]
    public array $entities;

    public function __construct(int $width, int $height, int $levels) {
        $this->width = $width;
        $this->height = $height;
        $this->levels = $levels;
        $this->tiles = [];
        $this->entities = [];
    }

    public static function loadFromJson(string $path): Map {
        $json = json_decode(file_get_contents($path), true);
        $map = new self($json['width'], $json['height'], $json['levels']);
        
        // Initialize tiles
        for ($z = 0; $z < $map->levels; $z++) {
            for ($y = 0; $y < $map->height; $y++) {
                for ($x = 0; $x < $map->width; $x++) {
                    $map->tiles[$z][$y][$x] = null;
                }
            }
        }

        foreach ($json['tiles'] as $tile) {
            $map->tiles[$tile['z']][$tile['y']][$tile['x']] = $tile;
        }

        foreach ($json['entities'] as $entityData) {
            $map->entities[] = new Entity($entityData['type'], $entityData['position'], $entityData['hp']);
        }

        return $map;
    }

    public function getTile(int $x, int $y, int $z): ?array {
        return $this->tiles[$z][$y][$x] ?? null;
    }

    public function isWalkable(int $x, int $y, int $z): bool {
        $tile = $this->getTile($x, $y, $z);
        if (!$tile || !$tile['walkable']) {
            return false;
        }
        
        // Check for impassable entities (enemies) - actually in this game movement stops at combat, but can we walk INTO them?
        // Rules say: "Movement halts at combat encounters". "Trigger: Automatic when hero enters enemy-occupied tile".
        // So enemies are technically walkable but trigger combat.
        return true;
    }
}

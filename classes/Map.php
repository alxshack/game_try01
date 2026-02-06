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
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new Exception("Could not read map file: $path");
        }
        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in map file: " . json_last_error_msg());
        }
        
        if (!isset($json['width'], $json['height'], $json['levels'], $json['tiles'])) {
            throw new Exception("Missing required fields in map JSON");
        }
        
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

    public static function getFirstMapPath(): ?string {
        $mapsDir = dirname(__DIR__) . '/data/maps';
        if (!is_dir($mapsDir)) {
            return null;
        }
        
        $files = scandir($mapsDir);
        $mapFiles = [];
        foreach ($files as $file) {
            if (preg_match('/map(\d+)\.json/i', $file, $matches)) {
                $mapFiles[(int)$matches[1]] = $mapsDir . '/' . $file;
            }
        }
        
        if (empty($mapFiles)) {
            return null;
        }
        
        ksort($mapFiles);
        return reset($mapFiles);
    }

    public static function getMapPath(int $level): ?string {
        $mapsDir = dirname(__DIR__) . '/data/maps';
        
        // Try exact match with leading zero (map01.json, map02.json...)
        $padded = str_pad($level, 2, '0', STR_PAD_LEFT);
        $paths = [
            "$mapsDir/map$level.json",
            "$mapsDir/map$padded.json"
        ];
        
        foreach ($paths as $p) {
            if (file_exists($p)) return $p;
        }
        
        // Fallback: search in directory case-insensitively
        if (is_dir($mapsDir)) {
            foreach (scandir($mapsDir) as $file) {
                if (preg_match("/^map0*$level\.json$/i", $file)) {
                    return $mapsDir . '/' . $file;
                }
            }
        }
        
        return null;
    }

    public function getTile(int $x, int $y, int $z): ?array {
        return $this->tiles[$z][$y][$x] ?? null;
    }

    public function isWalkable(int $x, int $y, int $z): bool {
        $tile = $this->getTile($x, $y, $z);
        if (!$tile || !$tile['walkable']) {
            return false;
        }
        
        // Враги (сущности) проверяются динамически в Pathfinder.
        // Статическая проходимость плитки зависит только от её типа.
        return true;
    }
}

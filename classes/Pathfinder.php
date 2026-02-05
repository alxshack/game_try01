<?php

class Pathfinder {
    public static function findPath(Map $map, array $start, array $end, array $enemies = []): ?array {
        $openSet = [];
        $closedSet = [];
        
        $startNode = [
            'x' => $start['x'],
            'y' => $start['y'],
            'z' => $start['z'],
            'g' => 0,
            'h' => self::heuristic($start, $end),
            'parent' => null
        ];
        
        $openSet[] = $startNode;
        
        while (!empty($openSet)) {
            // Find node with lowest f = g + h
            $currentIndex = 0;
            foreach ($openSet as $i => $node) {
                if ($node['g'] + $node['h'] < $openSet[$currentIndex]['g'] + $openSet[$currentIndex]['h']) {
                    $currentIndex = $i;
                }
            }
            
            $current = $openSet[$currentIndex];
            
            if ($current['x'] === $end['x'] && $current['y'] === $end['y'] && $current['z'] === $end['z']) {
                $path = [];
                while ($current !== null) {
                    $path[] = ['x' => $current['x'], 'y' => $current['y'], 'z' => $current['z']];
                    $current = $current['parent'];
                }
                return array_reverse($path);
            }
            
            array_splice($openSet, $currentIndex, 1);
            $closedSet[] = $current;
            
            foreach (self::getNeighbors($map, $current, $enemies) as $neighbor) {
                if (self::isInList($closedSet, $neighbor)) {
                    continue;
                }
                
                $tentativeG = $current['g'] + 1;
                
                $neighborNode = self::findInList($openSet, $neighbor);
                if ($neighborNode === null) {
                    $neighbor['g'] = $tentativeG;
                    $neighbor['h'] = self::heuristic($neighbor, $end);
                    $neighbor['parent'] = $current;
                    $openSet[] = $neighbor;
                } elseif ($tentativeG < $neighborNode['g']) {
                    $neighborNode['g'] = $tentativeG;
                    $neighborNode['parent'] = $current;
                }
            }
        }
        
        return null;
    }

    private static function heuristic(array $a, array $b): int {
        return abs($a['x'] - $b['x']) + abs($a['y'] - $b['y']) + abs($a['z'] - $b['z']) * 10; // Penalize level changes a bit
    }

    private static function getNeighbors(Map $map, array $node, array $enemies): array {
        $neighbors = [];
        $directions = [
            ['x' => 0, 'y' => 1], ['x' => 0, 'y' => -1],
            ['x' => 1, 'y' => 0], ['x' => -1, 'y' => 0]
        ];
        
        $x = $node['x'];
        $y = $node['y'];
        $z = $node['z'];

        foreach ($directions as $dir) {
            $nx = $x + $dir['x'];
            $ny = $y + $dir['y'];
            
            if ($nx < 0 || $nx >= $map->width || $ny < 0 || $ny >= $map->height) continue;

            // 1. Движение на текущем уровне
            // "все элементы, кроме лестниц, находящиеся на уровне z героя +1 являются помехой"
            $tileAbove = $map->getTile($nx, $ny, $z + 1);
            $isBlockedByAbove = ($tileAbove && strpos($tileAbove['type'], 'stairs') === false);
            
            if (!$isBlockedByAbove && $map->isWalkable($nx, $ny, $z)) {
                $targetTile = $map->getTile($nx, $ny, $z);
                // "при прохождении лестницы значение z героя меняется"
                if ($targetTile['type'] === 'stairs_up') {
                    if ($z + 1 < $map->levels && $map->isWalkable($nx, $ny, $z + 1)) {
                        $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z + 1];
                    }
                } elseif ($targetTile['type'] === 'stairs_down') {
                    if ($z > 0 && $map->isWalkable($nx, $ny, $z - 1)) {
                        $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z - 1];
                    }
                } else {
                    $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z];
                }
            }

            // 2. Переход на другой уровень через примыкающую лестницу (если текущий заблокирован или просто как альтернатива)
            // "на элементы с уровнем высоты, отличным от текущего... можно переместиться, передвигаясь по примыкающим элементам 'лестница'"
            
            // Вверх
            $tileUp = $map->getTile($nx, $ny, $z + 1);
            if ($tileUp && strpos($tileUp['type'], 'stairs') !== false && $map->isWalkable($nx, $ny, $z + 1)) {
                $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z + 1];
            }
            
            // Вниз
            if ($z > 0) {
                $tileDown = $map->getTile($nx, $ny, $z - 1);
                if ($tileDown && strpos($tileDown['type'], 'stairs') !== false && $map->isWalkable($nx, $ny, $z - 1)) {
                    $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z - 1];
                }
            }
        }
        
        // Remove duplicates if any
        $uniqueNeighbors = [];
        foreach ($neighbors as $n) {
            $key = "{$n['x']},{$n['y']},{$n['z']}";
            $uniqueNeighbors[$key] = $n;
        }
        
        return array_values($uniqueNeighbors);
    }

    private static function isInList(array $list, array $node): bool {
        foreach ($list as $item) {
            if ($item['x'] === $node['x'] && $item['y'] === $node['y'] && $item['z'] === $node['z']) {
                return true;
            }
        }
        return false;
    }

    private static function findInList(array &$list, array $node): ?array {
        foreach ($list as &$item) {
            if ($item['x'] === $node['x'] && $item['y'] === $node['y'] && $item['z'] === $node['z']) {
                return $item;
            }
        }
        return null;
    }
}

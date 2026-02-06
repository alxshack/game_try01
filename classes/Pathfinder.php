<?php

class Pathfinder {
    /**
     * Находит путь от точки start до точки end.
     * Враги блокируют построение маршрута, если их нельзя обойти,
     * за исключением случая, когда враг находится в целевой точке.
     */
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
            // Ищем узел с минимальным f = g + h
            $currentIndex = 0;
            foreach ($openSet as $i => $node) {
                if ($node['g'] + $node['h'] < $openSet[$currentIndex]['g'] + $openSet[$currentIndex]['h']) {
                    $currentIndex = $i;
                }
            }
            
            $current = $openSet[$currentIndex];
            
            // Если достигли цели
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
            
            foreach (self::getNeighbors($map, $current, $enemies, $end) as $neighbor) {
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
        return abs($a['x'] - $b['x']) + abs($a['y'] - $b['y']) + abs($a['z'] - $b['z']) * 10;
    }

    private static function getNeighbors(Map $map, array $node, array $enemies, array $target): array {
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

            $tileAbove = $map->getTile($nx, $ny, $z + 1);
            $isBlockedByAbove = ($tileAbove && strpos($tileAbove['type'], 'stairs') === false);
            
            if (!$isBlockedByAbove && $map->isWalkable($nx, $ny, $z)) {
                $targetTile = $map->getTile($nx, $ny, $z);
                $isStair = (strpos($targetTile['type'], 'stairs') !== false);
                
                if ($isStair) {
                    $stairMoved = false;
                    if ($z + 1 < $map->levels && $map->isWalkable($nx, $ny, $z + 1)) {
                        $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z + 1];
                        $stairMoved = true;
                    }
                    if ($z > 0 && $map->isWalkable($nx, $ny, $z - 1)) {
                        $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z - 1];
                        $stairMoved = true;
                    }
                    if (!$stairMoved) {
                        $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z];
                    }
                } else {
                    $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z];
                }
            }

            $tileUp = $map->getTile($nx, $ny, $z + 1);
            if ($tileUp && strpos($tileUp['type'], 'stairs') !== false && $map->isWalkable($nx, $ny, $z + 1)) {
                $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z + 1];
            }
            
            if ($z > 0) {
                $tileDown = $map->getTile($nx, $ny, $z - 1);
                if ($tileDown && strpos($tileDown['type'], 'stairs') !== false && $map->isWalkable($nx, $ny, $z - 1)) {
                    $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z - 1];
                }
            }
        }
        
        $uniqueNeighbors = [];
        foreach ($neighbors as $n) {
            // Если на клетке есть враг, и это не наша конечная цель, то проходить сквозь неё нельзя.
            if (self::hasEnemy($enemies, $n) && !self::isSamePos($n, $target)) {
                continue;
            }

            $key = "{$n['x']},{$n['y']},{$n['z']}";
            $uniqueNeighbors[$key] = $n;
        }
        
        return array_values($uniqueNeighbors);
    }

    private static function hasEnemy(array $enemies, array $pos): bool {
        foreach ($enemies as $enemy) {
            if ($enemy->position['x'] === $pos['x'] && 
                $enemy->position['y'] === $pos['y'] && 
                $enemy->position['z'] === $pos['z']) {
                return true;
            }
        }
        return false;
    }

    private static function isSamePos(array $a, array $b): bool {
        return $a['x'] === $b['x'] && $a['y'] === $b['y'] && $a['z'] === $b['z'];
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

<?php

class Pathfinder {
    /**
     * Находит путь от точки start до точки end.
     * Враги блокируют построение маршрута, если их нельзя обойти,
     * за исключением случая, когда враг находится в целевой точке.
     *
     * @param array $visibleTiles Массив видимых плиток [{x,y,z}, ...]
     */
    public static function findPath(Map $map, array $start, array $end, array $enemies = [], array $visibleTiles = []): ?array {
        $openSet = [];
        $closedSet = [];

        // Создаём множество видимых плиток для быстрой проверки
        $visibleSet = [];
        foreach ($visibleTiles as $tile) {
            $key = "{$tile['x']},{$tile['y']},{$tile['z']}";
            $visibleSet[$key] = true;
        }

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
            if ((int)$current['x'] === (int)$end['x'] && (int)$current['y'] === (int)$end['y'] && (int)$current['z'] === (int)$end['z']) {
                $path = [];
                while ($current !== null) {
                    $path[] = ['x' => $current['x'], 'y' => $current['y'], 'z' => $current['z']];
                    $current = $current['parent'];
                }
                return array_reverse($path);
            }

            array_splice($openSet, $currentIndex, 1);
            $closedSet[] = $current;

            foreach (self::getNeighbors($map, $current, $enemies, $end, $visibleSet) as $neighbor) {
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

        // Путь до цели не найден - возвращаем null
        return null;
    }

    private static function heuristic(array $a, array $b): int {
        return abs($a['x'] - $b['x']) + abs($a['y'] - $b['y']) + abs($a['z'] - $b['z']) * 10;
    }

    private static function getNeighbors(Map $map, array $node, array $enemies, array $target, array $visibleSet = []): array {
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

            // Проверяем видимость плитки (туман войны)
            $tileKey = "{$nx},{$ny},{$z}";
            if (!empty($visibleSet) && !isset($visibleSet[$tileKey])) {
                continue; // Плитка в тумане войны - нельзя пройти
            }

            $tileAbove = ($z + 1 < $map->levels) ? $map->getTile($nx, $ny, $z + 1) : null;
            // Клетка сверху блокирует проход, если она существует и НЕ является лестницей.
            // Примечание: если на клетке сверху стоит стена, то она блокирует проход по клетке снизу.
            $isBlockedByAbove = ($tileAbove && strpos($tileAbove['type'] ?? '', 'stairs') === false);

            if (!$isBlockedByAbove && $map->isWalkable($nx, $ny, $z)) {
                $targetTile = $map->getTile($nx, $ny, $z);
                if ($targetTile && strpos($targetTile['type'] ?? '', 'stairs') !== false) {
                    // Если мы стоим на лестнице, мы можем подняться или спуститься
                    if ($z + 1 < $map->levels && $map->isWalkable($nx, $ny, $z + 1)) {
                        $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z + 1];
                    }
                    if ($z > 0 && $map->isWalkable($nx, $ny, $z - 1)) {
                        $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z - 1];
                    }
                    // Также можно остаться на том же уровне
                    $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z];
                } else {
                    $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z];
                }
            }

            // Если соседняя клетка уровнем выше или ниже — это лестница, на неё можно перейти
            if ($z + 1 < $map->levels) {
                $tileUp = $map->getTile($nx, $ny, $z + 1);
                if ($tileUp && strpos($tileUp['type'] ?? '', 'stairs') !== false && $map->isWalkable($nx, $ny, $z + 1)) {
                    $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z + 1];
                }
            }
            if ($z > 0) {
                $tileDown = $map->getTile($nx, $ny, $z - 1);
                if ($tileDown && strpos($tileDown['type'] ?? '', 'stairs') !== false && $map->isWalkable($nx, $ny, $z - 1)) {
                    $neighbors[] = ['x' => $nx, 'y' => $ny, 'z' => $z - 1];
                }
            }
        }

        $uniqueNeighbors = [];
        foreach ($neighbors as $n) {
            // Если на клетке есть враг, и это не наша конечная цель, то проходить сквозь неё нельзя.
            $enemy = self::getEnemyAt($enemies, $n);
            if ($enemy && !self::isSamePos($n, $target)) {
                continue; // Враг блокирует путь
            }

            $key = "{$n['x']},{$n['y']},{$n['z']}";
            $uniqueNeighbors[$key] = $n;
        }

        return array_values($uniqueNeighbors);
    }

    private static function getEnemyAt(array $enemies, array $pos): ?Entity {
        foreach ($enemies as $enemy) {
            if (self::isSamePos($enemy->position, $pos)) {
                return $enemy;
            }
        }
        return null;
    }

    private static function hasEnemy(array $enemies, array $pos): bool {
        return self::getEnemyAt($enemies, $pos) !== null;
    }

    private static function isSamePos(array $a, array $b): bool {
        return (int)$a['x'] === (int)$b['x'] && (int)$a['y'] === (int)$b['y'] && (int)$a['z'] === (int)$b['z'];
    }

    private static function isInList(array $list, array $node): bool {
        foreach ($list as $item) {
            if (self::isSamePos($item, $node)) {
                return true;
            }
        }
        return false;
    }

    private static function findInList(array &$list, array $node): ?array {
        foreach ($list as &$item) {
            if (self::isSamePos($item, $node)) {
                return $item;
            }
        }
        return null;
    }
}

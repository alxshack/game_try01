<?php
require_once __DIR__ . '/../api/bootstrap.php';

/**
 * Переработанный генератор карт для HP Accumulator.
 * Создает комнаты, соединенные проходами, некоторые из которых заняты врагами.
 * Проверяет проходимость с учетом накопления HP.
 */

class Room {
    public $id;
    public $x1, $y1, $x2, $y2;
    public $center_x, $center_y;
    public $enemies = [];
    public $hasExit = false;

    public function __construct($id, $x1, $y1, $w, $h) {
        $this->id = $id;
        $this->x1 = $x1;
        $this->y1 = $y1;
        $this->x2 = $x1 + $w - 1;
        $this->y2 = $y1 + $h - 1;
        $this->center_x = (int)($x1 + $w / 2);
        $this->center_y = (int)($y1 + $h / 2);
    }

    public function intersects(Room $other) {
        return !($this->x2 < $other->x1 || $this->x1 > $other->x2 ||
                 $this->y2 < $other->y1 || $this->y1 > $other->y2);
    }
}

class MapGenerator {
    private $width = 41; 
    private $height = 41;
    private $levels = 1;
    private $tiles = [];
    private $rooms = [];
    private $entities = [];
    private $passages = []; 

    public function generate($targetFile) {
        $this->tiles = [];
        $this->rooms = [];
        $this->entities = [];
        $this->passages = [];

        for ($z = 0; $z < $this->levels; $z++) {
            for ($y = 0; $y < $this->height; $y++) {
                for ($x = 0; $x < $this->width; $x++) {
                    $this->tiles[$z][$y][$x] = [
                        'x' => $x, 'y' => $y, 'z' => $z,
                        'type' => 'wall',
                        'walkable' => false,
                        'texture' => 'stone_floor'
                    ];
                }
            }
        }

        // 1. Создание комнат
        $this->createRooms();
        if (count($this->rooms) < 5) return false;

        // 2. Соединение комнат
        $this->connectRooms();

        // 3. Выбор максимально удаленных комнат для Героя и Выхода
        $this->assignStartAndExit();

        $startRoom = $this->rooms[0];
        $this->entities[] = [
            'type' => 'hero',
            'position' => ['x' => $startRoom->center_x, 'y' => $startRoom->center_y, 'z' => 0],
            'hp' => 10
        ];

        $exitRoom = $this->rooms[count($this->rooms) - 1];
        $exitRoom->hasExit = true;
        $this->tiles[0][$exitRoom->center_y][$exitRoom->center_x]['type'] = 'exit';
        $this->tiles[0][$exitRoom->center_y][$exitRoom->center_x]['walkable'] = true;

        // 4. Расстановка врагов в проходах и комнатах (включая боссов)
        $this->placeEnemies();

        // 5. Валидация
        if ($this->validatePath()) {
            $this->save($targetFile);
            return true;
        }
        return false;
    }

    private function createRooms() {
        $attempts = 0;
        $maxRooms = 15;
        while (count($this->rooms) < $maxRooms && $attempts < 500) {
            $attempts++;
            $w = rand(5, 9);
            $h = rand(5, 9);
            $x = rand(1, $this->width - $w - 1);
            $y = rand(1, $this->height - $h - 1);
            $newRoom = new Room(count($this->rooms), $x, $y, $w, $h);

            $overlap = false;
            foreach ($this->rooms as $r) {
                if ($newRoom->intersects($r)) {
                    $overlap = true;
                    break;
                }
            }

            if (!$overlap) {
                for ($ry = $newRoom->y1; $ry <= $newRoom->y2; $ry++) {
                    for ($rx = $newRoom->x1; $rx <= $newRoom->x2; $rx++) {
                        $this->tiles[0][$ry][$rx]['type'] = 'floor';
                        $this->tiles[0][$ry][$rx]['walkable'] = true;
                    }
                }
                $this->rooms[] = $newRoom;
            }
        }
    }

    private function connectRooms() {
        for ($i = 0; $i < count($this->rooms) - 1; $i++) {
            $this->hCorridor($this->rooms[$i]->center_x, $this->rooms[$i+1]->center_x, $this->rooms[$i]->center_y, $i, $i+1);
            $this->vCorridor($this->rooms[$i]->center_y, $this->rooms[$i+1]->center_y, $this->rooms[$i+1]->center_x, $i, $i+1);
        }
    }

    private function hCorridor($x1, $x2, $y, $r1, $r2) {
        for ($x = min($x1, $x2); $x <= max($x1, $x2); $x++) {
            if ($this->tiles[0][$y][$x]['type'] === 'wall') {
                $this->tiles[0][$y][$x]['type'] = 'floor';
                $this->tiles[0][$y][$x]['walkable'] = true;
                if ($this->isNarrow($x, $y)) {
                    $this->passages[] = ['x' => $x, 'y' => $y, 'r1' => $r1, 'r2' => $r2];
                }
            }
        }
    }

    private function vCorridor($y1, $y2, $x, $r1, $r2) {
        for ($y = min($y1, $y2); $y <= max($y1, $y2); $y++) {
            if ($this->tiles[0][$y][$x]['type'] === 'wall') {
                $this->tiles[0][$y][$x]['type'] = 'floor';
                $this->tiles[0][$y][$x]['walkable'] = true;
                if ($this->isNarrow($x, $y)) {
                    $this->passages[] = ['x' => $x, 'y' => $y, 'r1' => $r1, 'r2' => $r2];
                }
            }
        }
    }

    private function isNarrow($x, $y) {
        $hWalls = ($this->tiles[0][$y][$x-1]['type'] === 'wall' && $this->tiles[0][$y][$x+1]['type'] === 'wall');
        $vWalls = ($this->tiles[0][$y-1][$x]['type'] === 'wall' && $this->tiles[0][$y+1][$x]['type'] === 'wall');
        return $hWalls || $vWalls;
    }

    private function assignStartAndExit() {
        $maxDist = -1;
        $startIdx = 0;
        $exitIdx = count($this->rooms) - 1;

        for ($i = 0; $i < count($this->rooms); $i++) {
            for ($j = $i + 1; $j < count($this->rooms); $j++) {
                $r1 = $this->rooms[$i];
                $r2 = $this->rooms[$j];
                $dist = sqrt(pow($r1->center_x - $r2->center_x, 2) + pow($r1->center_y - $r2->center_y, 2));
                if ($dist > $maxDist) {
                    $maxDist = $dist;
                    $startIdx = $i;
                    $exitIdx = $j;
                }
            }
        }

        // Переставляем комнаты: выбраная стартовая в начало, выбранная выходная в конец
        $startRoom = $this->rooms[$startIdx];
        $exitRoom = $this->rooms[$exitIdx];
        
        unset($this->rooms[$startIdx]);
        if ($startIdx != $exitIdx) {
            unset($this->rooms[$exitIdx]);
        }
        
        $this->rooms = array_values($this->rooms);
        array_unshift($this->rooms, $startRoom);
        $this->rooms[] = $exitRoom;
    }

    private function placeEnemies() {
        $exitRoomId = $this->rooms[count($this->rooms) - 1]->id;
        
        $usedPassages = [];
        foreach ($this->passages as $p) {
            $key = $p['x'] . ',' . $p['y'];
            if (isset($usedPassages[$key])) continue;
            
            // Определяем, ведет ли этот проход в комнату с выходом
            $isExitPassage = ($p['r1'] === $exitRoomId || $p['r2'] === $exitRoomId);
            // Проход "посередине" (условно, если это не первая и не последняя комната в цепочке создания)
            $isMiddlePassage = ($p['r1'] > 2 && $p['r2'] < count($this->rooms) - 2);

            if ($isExitPassage) {
                // Босс, охраняющий выход
                $hp = rand(200, 400);
                $this->entities[] = [
                    'type' => 'boss',
                    'position' => ['x' => $p['x'], 'y' => $p['y'], 'z' => 0],
                    'hp' => $hp,
                    'isPassage' => true,
                    'r1' => $p['r1'],
                    'r2' => $p['r2']
                ];
                $usedPassages[$key] = true;
            } elseif ($isMiddlePassage && rand(0, 100) < 20) {
                // Босс в середине карты
                $hp = rand(100, 250);
                $this->entities[] = [
                    'type' => 'boss',
                    'position' => ['x' => $p['x'], 'y' => $p['y'], 'z' => 0],
                    'hp' => $hp,
                    'isPassage' => true,
                    'r1' => $p['r1'],
                    'r2' => $p['r2']
                ];
                $usedPassages[$key] = true;
            } elseif (rand(0, 100) < 80) {
                $hp = rand(10, 60);
                $this->entities[] = [
                    'type' => 'guard',
                    'position' => ['x' => $p['x'], 'y' => $p['y'], 'z' => 0],
                    'hp' => $hp,
                    'isPassage' => true,
                    'r1' => $p['r1'],
                    'r2' => $p['r2']
                ];
                $usedPassages[$key] = true;
            }
        }

        foreach ($this->rooms as $room) {
            $numEnemies = rand(2, 4);
            for ($i = 0; $i < $numEnemies; $i++) {
                $ex = rand($room->x1, $room->x2);
                $ey = rand($room->y1, $room->y2);
                if ($this->tiles[0][$ey][$ex]['type'] === 'floor' && $this->tiles[0][$ey][$ex]['walkable']) {
                    if ($this->isOccupied($ex, $ey)) continue;
                    
                    $hp = rand(5, 40);
                    $this->entities[] = [
                        'type' => 'monster',
                        'position' => ['x' => $ex, 'y' => $ey, 'z' => 0],
                        'hp' => $hp,
                        'roomId' => $room->id
                    ];
                }
            }
        }
    }

    private function isOccupied($x, $y) {
        foreach ($this->entities as $e) {
            if ($e['position']['x'] == $x && $e['position']['y'] == $y) return true;
        }
        $tile = $this->tiles[0][$y][$x];
        if ($tile['type'] === 'exit') return true;
        return false;
    }

    private function validatePath() {
        $hp = 10;
        $visitedRooms = [0 => true];
        $enemies = $this->entities;
        foreach ($enemies as $k => $e) {
            if ($e['type'] === 'hero') {
                unset($enemies[$k]);
                break;
            }
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            $bestEnemyKey = null;
            $minHp = 999999;

            foreach ($enemies as $k => $e) {
                $canReach = false;
                if (isset($e['roomId'])) {
                    if (isset($visitedRooms[$e['roomId']])) $canReach = true;
                } elseif (isset($e['isPassage'])) {
                    if (isset($visitedRooms[$e['r1']]) || isset($visitedRooms[$e['r2']])) $canReach = true;
                }

                if ($canReach) {
                    if ($e['hp'] < $minHp) {
                        $minHp = $e['hp'];
                        $bestEnemyKey = $k;
                    }
                }
            }

            if ($bestEnemyKey !== null && $minHp < $hp) {
                $e = $enemies[$bestEnemyKey];
                $hp += $e['hp'];
                if (isset($e['isPassage'])) {
                    $visitedRooms[$e['r1']] = true;
                    $visitedRooms[$e['r2']] = true;
                }
                unset($enemies[$bestEnemyKey]);
                $changed = true;
            }
        }

        $exitRoomId = count($this->rooms) - 1;
        return isset($visitedRooms[$exitRoomId]);
    }

    private function save($filename) {
        $flatTiles = [];
        for ($z = 0; $z < $this->levels; $z++) {
            for ($y = 0; $y < $this->height; $y++) {
                for ($x = 0; $x < $this->width; $x++) {
                    if ($this->tiles[$z][$y][$x]['type'] !== 'empty' || $z == 0) {
                        $flatTiles[] = $this->tiles[$z][$y][$x];
                    }
                }
            }
        }

        $data = [
            'width' => $this->width,
            'height' => $this->height,
            'levels' => $this->levels,
            'tiles' => $flatTiles,
            'entities' => array_values($this->entities)
        ];

        $path = __DIR__ . '/../data/maps/' . $filename;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        echo "Map saved to $path\n";
    }
}

$targetFile = $argv[1] ?? 'map01.json';
if (substr($targetFile, -5) !== '.json') $targetFile .= '.json';

$gen = new MapGenerator();
$attempts = 0;
while ($attempts < 200) {
    $attempts++;
    if ($gen->generate($targetFile)) {
        echo "Successfully generated map $targetFile in $attempts attempts.\n";
        exit(0);
    }
}
echo "Failed to generate valid map.\n";
exit(1);

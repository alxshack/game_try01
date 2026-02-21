<?php

class Entity {
    public string $type;
    public array $position; // ['x' => 0, 'y' => 0, 'z' => 0]
    public int $hp;
    public string $color = '#f22'; // Default red

    public function __construct(string $type, array $position, int $hp, array $data = []) {
        $this->type = $type;
        $this->position = $position;
        $this->hp = $hp;
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public static function create(array $data): Entity {
        $type = $data['type'] ?? 'unknown';
        $position = $data['position'] ?? ['x' => 0, 'y' => 0, 'z' => 0];
        $hp = $data['hp'] ?? 0;
        
        // Remove known fields to avoid duplication in dynamic properties
        $extra = $data;
        unset($extra['type'], $extra['position'], $extra['hp']);

        switch ($type) {
            case 'hero': return new Hero($position, $hp, $extra);
            case 'guard': return new Guard($position, $hp, $extra);
            case 'monster': return new Monster($position, $hp, $extra);
            case 'boss': return new Boss($position, $hp, $extra);
            default: return new Entity($type, $position, $hp, $extra);
        }
    }

    public function toArray(): array {
        $res = get_object_vars($this);
        return $res;
    }
}

class Hero extends Entity {
    public function __construct(array $position, int $hp, array $data = []) {
        parent::__construct('hero', $position, $hp, $data);
        $this->color = '#eee';
    }
}

class Guard extends Entity {
    public function __construct(array $position, int $hp, array $data = []) {
        parent::__construct('guard', $position, $hp, $data);
        $this->color = '#3498db'; // Blue
    }
}

class Monster extends Entity {
    public function __construct(array $position, int $hp, array $data = []) {
        parent::__construct('monster', $position, $hp, $data);
        $this->color = '#e74c3c'; // Red
    }
}

class Boss extends Entity {
    public function __construct(array $position, int $hp, array $data = []) {
        parent::__construct('boss', $position, $hp, $data);
        $this->color = '#9b59b6'; // Purple
    }
}

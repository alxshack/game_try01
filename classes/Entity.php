<?php

class Entity {
    public string $type;
    public array $position; // ['x' => 0, 'y' => 0, 'z' => 0]
    public int $hp;

    public function __construct(string $type, array $position, int $hp) {
        $this->type = $type;
        $this->position = $position;
        $this->hp = $hp;
    }

    public function toArray(): array {
        return [
            'type' => $this->type,
            'position' => $this->position,
            'hp' => $this->hp
        ];
    }
}

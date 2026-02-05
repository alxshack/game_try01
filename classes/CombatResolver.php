<?php

class CombatResolver {
    public static function resolve(Entity $hero, Entity $enemy): array {
        if ($hero->hp > $enemy->hp) {
            $result = 'victory';
        } elseif ($hero->hp === $enemy->hp) {
            $result = (rand(0, 1) === 1) ? 'victory' : 'defeat';
        } else {
            $result = 'defeat';
        }

        return [
            'result' => $result,
            'hero_hp_before' => $hero->hp,
            'enemy_hp' => $enemy->hp
        ];
    }
}

<?php

namespace App\Users\Services;

class PlateCalculatorService
{
    /**
     * Sensible home-gym defaults (kg inventory stored as grams).
     *
     * @return array{name: string, bars: list<array{name: string, weight_g: int, is_default: bool}>, plates: list<array{denomination_g: int, count: int, colour: ?string}>}
     */
    public static function defaultProfilePayload(): array
    {
        return [
            'name' => 'Home gym',
            'bars' => [
                ['name' => 'Olympic', 'weight_g' => 20000, 'is_default' => true],
            ],
            'plates' => [
                ['denomination_g' => 25000, 'count' => 2, 'colour' => 'red'],
                ['denomination_g' => 20000, 'count' => 2, 'colour' => 'blue'],
                ['denomination_g' => 15000, 'count' => 2, 'colour' => 'yellow'],
                ['denomination_g' => 10000, 'count' => 4, 'colour' => 'green'],
                ['denomination_g' => 5000, 'count' => 4, 'colour' => 'white'],
                ['denomination_g' => 2500, 'count' => 4, 'colour' => 'black'],
                ['denomination_g' => 1250, 'count' => 4, 'colour' => 'silver'],
            ],
        ];
    }
}

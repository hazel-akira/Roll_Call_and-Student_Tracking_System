<?php

namespace App\Support;

class DutyRosterCategories
{
    public const DINING_HALL = 'dining_hall';

    public const BOARDING = 'boarding';

    public const TUITION_AREAS = 'tuition_areas';

    public const GAMES = 'games';

    public const ENTERTAINMENT = 'entertainment';

    public const SUNDAY_SERVICE = 'sunday_service';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::DINING_HALL => 'Dining Hall',
            self::BOARDING => 'Boarding',
            self::TUITION_AREAS => 'Tuition Areas',
            self::GAMES => 'Games',
            self::ENTERTAINMENT => 'Entertainment',
            self::SUNDAY_SERVICE => 'Sunday Service',
        ];
    }

    public static function label(string $key): string
    {
        return self::labels()[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Standard rows matching the institutional weekly duty sheet layout.
     *
     * @return list<array{category: string, location: string|null, time_slot: string|null, sort_order: int}>
     */
    public static function standardTemplate(): array
    {
        return [
            ['category' => self::DINING_HALL, 'location' => null, 'time_slot' => null, 'sort_order' => 10],
            ['category' => self::BOARDING, 'location' => 'G.10', 'time_slot' => null, 'sort_order' => 20],
            ['category' => self::BOARDING, 'location' => 'F3', 'time_slot' => null, 'sort_order' => 30],
            ['category' => self::BOARDING, 'location' => 'F4', 'time_slot' => null, 'sort_order' => 40],
            ['category' => self::TUITION_AREAS, 'location' => 'G.10', 'time_slot' => null, 'sort_order' => 50],
            ['category' => self::TUITION_AREAS, 'location' => 'F3', 'time_slot' => null, 'sort_order' => 60],
            ['category' => self::TUITION_AREAS, 'location' => 'F4', 'time_slot' => '7:00 AM – 5:00 PM', 'sort_order' => 70],
            ['category' => self::TUITION_AREAS, 'location' => 'F4', 'time_slot' => '5:00 PM – 11:00 PM', 'sort_order' => 80],
            ['category' => self::GAMES, 'location' => 'Upper Field', 'time_slot' => null, 'sort_order' => 90],
            ['category' => self::GAMES, 'location' => 'Lower Field', 'time_slot' => null, 'sort_order' => 100],
            ['category' => self::ENTERTAINMENT, 'location' => null, 'time_slot' => null, 'sort_order' => 110],
            ['category' => self::SUNDAY_SERVICE, 'location' => 'CU', 'time_slot' => null, 'sort_order' => 120],
            ['category' => self::SUNDAY_SERVICE, 'location' => 'C.A.', 'time_slot' => null, 'sort_order' => 130],
        ];
    }
}

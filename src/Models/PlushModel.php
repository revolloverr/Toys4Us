<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class PlushModel
{
    public function getBases(): array
    {
        return R::findAll('plush_base', 'is_active = 1');
    }

    public function getSpecies(): array
    {
        $rows = R::getAll('SELECT DISTINCT species FROM plush_base WHERE is_active = 1');
        return array_column($rows, 'species');
    }

    public function getAccessoriesByCategory(string $category): array
    {
        return R::find('plush_accessory', 'category = ? AND is_active = 1', [$category]);
    }

    public function getAllAccessoriesGrouped(): array
    {
        $all = R::find('plush_accessory', 'is_active = 1 ORDER BY category, id');
        $grouped = ['facewear' => [], 'bodywear' => [], 'shoes' => []];
        foreach ($all as $acc) {
            $grouped[$acc->category][] = $acc;
        }
        return $grouped;
    }

    public function saveCustomPlush(
        ?int $userId,
        int $baseId,
        string $name,
        array $accessoryIds,
        float $totalPrice,
        ?string $voicePath = null
    ): int {
        $plush              = R::dispense('custom_plush');
        $plush->user_id     = $userId;
        $plush->base_id     = $baseId;
        $plush->name        = $name;
        $plush->total_price = $totalPrice;
        $plush->voice_message_path = $voicePath;
        $id = R::store($plush);

        foreach ($accessoryIds as $accId) {
            $junction = R::dispense('custom_plush_accessory');
            $junction->custom_plush_id = $id;
            $junction->accessory_id    = $accId;
            R::store($junction);
        }

        return (int) $id;
    }
}
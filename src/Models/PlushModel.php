<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class PlushModel
{
    // ── BASES ─────────────────────────────────────────────────────────────────

    public function getBases(): array
    {
        return R::findAll('plush_base', 'ORDER BY species, color');
    }

    public function getBaseById(int $id): ?\RedBeanPHP\OODBBean
    {
        return R::load('plush_base', $id) ?: null;
    }

    public function getSpecies(): array
    {
        $rows = R::getAll('SELECT DISTINCT species FROM plush_base WHERE is_active = 1');
        return array_column($rows, 'species');
    }

    public function getAllBasesForJs(): array
    {
        return R::getAll('SELECT * FROM plush_base WHERE is_active = 1 ORDER BY id');
    }

    public function createBase(string $name, string $species, string $color, string $imagePath, float $price, int $sortOrder): void
    {
        R::exec(
            'INSERT INTO plush_base (name, species, color, image_path, base_price, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$name, $species, $color, $imagePath, $price, $sortOrder]
        );
    }

    public function saveBase(mixed $bean): void
    {
        R::store($bean);
    }

    public function deleteBase(int $id): void
    {
        $base = R::load('plush_base', $id);
        if ($base->id) R::trash($base);
    }

    // ── ACCESSORIES ───────────────────────────────────────────────────────────

    public function getAllAccessories(): array
    {
        return R::findAll('plush_accessory', 'ORDER BY category, id');
    }

    public function getAllAccessoriesGrouped(): array
    {
        $all     = R::find('plush_accessory', 'is_active = 1 ORDER BY category, id');
        $grouped = ['facewear' => [], 'bodywear' => [], 'shoes' => []];
        foreach ($all as $acc) {
            $grouped[$acc->category][] = $acc;
        }
        return $grouped;
    }

    public function getAccessoriesByCategory(string $category): array
    {
        return R::find('plush_accessory', 'category = ? AND is_active = 1', [$category]);
    }

    public function getAccessoryById(int $id): ?\RedBeanPHP\OODBBean
    {
        return R::load('plush_accessory', $id) ?: null;
    }

    public function createAccessory(string $name, string $category, string $imagePath, float $price): void
    {
        R::exec(
            'INSERT INTO plush_accessory (name, category, image_path, price, is_active) VALUES (?, ?, ?, ?, 1)',
            [$name, $category, $imagePath, $price]
        );
    }

    public function saveAccessory(mixed $bean): void
    {
        R::store($bean);
    }

    public function deleteAccessory(int $id): void
    {
        $acc = R::load('plush_accessory', $id);
        if ($acc->id) R::trash($acc);
    }

    // ── CUSTOM PLUSH ──────────────────────────────────────────────────────────

    public function saveCustomPlush(
        ?int $userId,
        int $baseId,
        string $name,
        array $accessoryIds,
        float $totalPrice,
        ?string $voicePath = null
    ): int {
        $plush                     = R::dispense('custom_plush');
        $plush->user_id            = $userId;
        $plush->base_id            = $baseId;
        $plush->name               = $name;
        $plush->total_price        = $totalPrice;
        $plush->voice_message_path = $voicePath;
        $id                        = R::store($plush);

        R::exec('DELETE FROM custom_plush_accessory WHERE custom_plush_id = ?', [$id]);

        foreach ($accessoryIds as $accId) {
            if ($accId > 0) {
                $junction                    = R::dispense('custom_plush_accessory');
                $junction->custom_plush_id   = $id;
                $junction->accessory_id      = $accId;
                R::store($junction);
            }
        }

        return (int) $id;
    }

    public function getCustomPlushDetails(int $plushId): ?array
    {
        $plush = R::load('custom_plush', $plushId);
        if (!$plush->id) {
            return null;
        }

        $base          = R::load('plush_base', (int) $plush->base_id);
        $accessoryRows = R::getAll(
            'SELECT pa.id, pa.name, pa.image_path, pa.category, pa.price
             FROM plush_accessory pa
             JOIN custom_plush_accessory cpa ON cpa.accessory_id = pa.id
             WHERE cpa.custom_plush_id = ?',
            [$plushId]
        );

        $accessories = ['facewear' => null, 'bodywear' => null, 'shoes' => null];
        foreach ($accessoryRows as $row) {
            $accessories[$row['category']] = $row;
        }

        return [
            'id'            => (int) $plush->id,
            'name'          => $plush->name,
            'base_id'       => (int) $plush->base_id,
            'base_name'     => $base->name ?? 'Unknown',
            'base_image'    => $base->image_path ?? '',
            'base_species'  => $base->species ?? '',
            'base_color'    => $base->color ?? '',
            'total_price'   => (float) $plush->total_price,
            'accessories'   => $accessories,
            'accessory_ids' => array_column($accessoryRows, 'id'),
        ];
    }
}
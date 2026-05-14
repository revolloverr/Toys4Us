<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class PlushModel
{
    // ── BASES ─────────────────────────────────────────────────────────────────

    public function getBases(): array
    {
        return R::findAll('plushbase', 'ORDER BY species, color');
    }

    public function getBaseById(int $id): ?\RedBeanPHP\OODBBean
    {
        return R::load('plushbase', $id) ?: null;
    }

    public function getSpecies(): array
    {
        $rows = R::getAll('SELECT DISTINCT species FROM plushbase WHERE is_active = 1');
        return array_column($rows, 'species');
    }

    public function getAllBasesForJs(): array
    {
        return R::getAll('SELECT * FROM plushbase WHERE is_active = 1 ORDER BY id');
    }

    public function createBase(string $name, string $species, string $color, string $imagePath, float $price, int $sortOrder): void
    {
        R::exec(
            'INSERT INTO plushbase (name, species, color, image_path, base_price, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$name, $species, $color, $imagePath, $price, $sortOrder]
        );
    }

    public function saveBase(mixed $bean): void
    {
        R::store($bean);
    }

    public function deleteBase(int $id): void
    {
        $base = R::load('plushbase', $id);
        if ($base->id) R::trash($base);
    }

    // ── ACCESSORIES ───────────────────────────────────────────────────────────

    public function getAllAccessories(): array
    {
        return R::findAll('plushaccessory', 'ORDER BY category, id');
    }

    public function getAllAccessoriesGrouped(): array
    {
        $all     = R::find('plushaccessory', 'is_active = 1 ORDER BY category, id');
        $grouped = ['facewear' => [], 'bodywear' => [], 'shoes' => []];
        foreach ($all as $acc) {
            $grouped[$acc->category][] = $acc;
        }
        return $grouped;
    }

    public function getAccessoriesByCategory(string $category): array
    {
        return R::find('plushaccessory', 'category = ? AND is_active = 1', [$category]);
    }

    public function getAccessoryById(int $id): ?\RedBeanPHP\OODBBean
    {
        return R::load('plushaccessory', $id) ?: null;
    }

    public function createAccessory(string $name, string $category, string $imagePath, float $price): void
    {
        R::exec(
            'INSERT INTO plushaccessory (name, category, image_path, price, is_active) VALUES (?, ?, ?, ?, 1)',
            [$name, $category, $imagePath, $price]
        );
    }

    public function saveAccessory(mixed $bean): void
    {
        R::store($bean);
    }

    public function deleteAccessory(int $id): void
    {
        $acc = R::load('plushaccessory', $id);
        if ($acc->id) R::trash($acc);
    }

    // ── CUSTOM PLUSH ──────────────────────────────────────────────────────────

    public function saveCustomPlush(?int $userId, int $baseId, string $name, array $accessoryIds, float $totalPrice, ?string $voicePath = null): int
    {
        R::exec(
            'INSERT INTO customplush (user_id, base_id, name, total_price, voice_message_path) VALUES (?, ?, ?, ?, ?)',
            [$userId, $baseId, $name, $totalPrice, $voicePath]
        );
        $id = (int) R::getInsertID();

        foreach ($accessoryIds as $accId) {
            if ($accId > 0) {
                R::exec(
                    'INSERT INTO customplushaccessory (customplush_id, accessory_id) VALUES (?, ?)',
                    [$id, $accId]
                );
            }
        }

        return $id;
    }

    public function getCustomPlushDetails(int $plushId): ?array
    {
        $plush = R::load('customplush', $plushId);
        if (!$plush->id) {
            return null;
        }

        $base          = R::load('plushbase', (int) $plush->base_id);
        $accessoryRows = R::getAll(
            'SELECT pa.id, pa.name, pa.image_path, pa.category, pa.price
             FROM plushaccessory pa
             JOIN customplushaccessory cpa ON cpa.accessory_id = pa.id
             WHERE cpa.customplush_id = ?',
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

    public function updateCustomPlush(int $plushId, int $baseId, string $name, array $accessoryIds, float $totalPrice): void
    {
        R::exec(
            'UPDATE customplush SET base_id = ?, name = ?, total_price = ? WHERE id = ?',
            [$baseId, $name, $totalPrice, $plushId]
        );

        R::exec('DELETE FROM customplushaccessory WHERE customplush_id = ?', [$plushId]);

        foreach ($accessoryIds as $accId) {
            if ($accId > 0) {
                R::exec(
                    'INSERT INTO customplushaccessory (customplush_id, accessory_id) VALUES (?, ?)',
                    [$plushId, $accId]
                );
            }
        }
    }
}
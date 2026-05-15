<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class AddressModel
{
    public function create(int $userId, string $name, string $addressLine, string $city, string $province, string $postalCode): int
    {
        $addressBean = R::dispense('address');
        $addressBean->user_id = $userId;
        $addressBean->name = $name;
        $addressBean->address = $addressLine;
        $addressBean->city = $city;
        $addressBean->province = $province;
        $addressBean->postal_code = $postalCode;
        $addressBean->created_at = R::isoDateTime();
        return (int) R::store($addressBean);
    }

    public function findByUser(int $userId): array
    {
        return R::findAll('address', 'user_id = ? ORDER BY created_at DESC', [$userId]);
    }

    public function load(int $id): mixed
    {
        return R::load('address', $id);
    }

    public function save(mixed $bean): void
    {
        R::store($bean);
    }

    public function delete(int $id): void
    {
        $address = R::load('address', $id);
        if ($address->id) {
            R::trash($address);
        }
    }

    public function findByIdAndUser(int $id, int $userId): mixed
    {
        return R::findOne('address', 'id = ? AND user_id = ?', [$id, $userId]);
    }
}
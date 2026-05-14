<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class CartModel
{
    public function getByUser(int $userId): array
    {
        return R::getAll(
            'SELECT ci.*, p.name, p.price, p.image
             FROM cart_item ci
             JOIN product p ON p.id = ci.product_id
             WHERE ci.user_id = ?',
            [$userId]
        );
    }

    public function addItem(int $userId, int $productId, int $qty = 1): void
    {
        $existing = R::findOne('cart_item', 'user_id = ? AND product_id = ?', [$userId, $productId]);
        if ($existing) {
            $existing->quantity += $qty;
            R::store($existing);
        } else {
            $item              = R::dispense('cart_item');
            $item->user_id     = $userId;
            $item->product_id  = $productId;
            $item->quantity    = $qty;
            R::store($item);
        }
    }

    public function removeItem(int $userId, int $productId): void
    {
        $item = R::findOne('cart_item', 'user_id = ? AND product_id = ?', [$userId, $productId]);
        if ($item) R::trash($item);
    }

    public function clearByUser(int $userId): void
    {
        R::exec('DELETE FROM cart_item WHERE user_id = ?', [$userId]);
    }

    public function mergeSessionCart(int $userId, array $sessionCart): void
    {
        foreach ($sessionCart as $item) {
            if (!empty($item['id']) && empty($item['type'])) {
                $this->addItem($userId, (int) $item['id'], (int) ($item['qty'] ?? 1));
            }
        }
    }

    public function toSessionFormat(int $userId): array
    {
        $items  = $this->getByUser($userId);
        $result = [];
        foreach ($items as $item) {
            $result[$item['product_id']] = [
                'id'    => (int) $item['product_id'],
                'name'  => $item['name'],
                'price' => (float) $item['price'],
                'qty'   => (int) $item['quantity'],
            ];
        }
        return $result;
    }
}
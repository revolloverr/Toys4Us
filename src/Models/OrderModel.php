<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class OrderModel
{
    public function create(int $userId, float $total, string $status, ?string $stripePaymentId, ?int $addressId = null): int
    {
        // Some deployments/databases don't have an address_id column on the orders table.
        // Inspect the table columns at runtime and insert only the columns that exist.
        $cols = R::inspect('order');
        if (in_array('address_id', $cols, true)) {
            R::exec(
                'INSERT INTO `order` (user_id, total, status, stripe_payment_id, address_id) VALUES (?, ?, ?, ?, ?)',
                [$userId, $total, $status, $stripePaymentId, $addressId]
            );
        } else {
            R::exec(
                'INSERT INTO `order` (user_id, total, status, stripe_payment_id) VALUES (?, ?, ?, ?)',
                [$userId, $total, $status, $stripePaymentId]
            );
        }
        return (int) R::getInsertID();
    }

    public function addItem(int $orderId, ?int $productId, ?int $customPlushId, int $qty, float $price): void
    {
        R::exec(
            'INSERT INTO order_item (order_id, product_id, custom_plush_id, quantity, price) VALUES (?, ?, ?, ?, ?)',
            [$orderId, $productId, $customPlushId, $qty, $price]
        );
    }

    public function findByUser(int $userId): array
    {
        return R::getAll(
            'SELECT * FROM `order` WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }

    public function getItems(int $orderId): array
    {
        return R::getAll(
            'SELECT oi.*, p.name as product_name, p.image as product_image
             FROM order_item oi
             LEFT JOIN product p ON p.id = oi.product_id
             WHERE oi.order_id = ?',
            [$orderId]
        );
    }

    public function findAll(): array
    {
        return R::getAll('SELECT * FROM `order` ORDER BY created_at DESC');
    }

    public function updateStatus(int $orderId, string $status): void
    {
        R::exec('UPDATE `order` SET status = ? WHERE id = ?', [$status, $orderId]);
    }
}
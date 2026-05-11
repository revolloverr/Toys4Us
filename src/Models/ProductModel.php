<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

/**
 * ProductModel
 *
 * Data access layer for the products (toys) resource.
 * All RedBeanPHP R:: calls are contained here.
 * This is the Model layer of MVC.
 */

class ProductModel
{
    public function findAll(): array
    {
        return R::findAll('product', 'ORDER BY id ASC');
    }

    public function create(string $name, string $description, float $price, string $image = ''): void
    {
        $product           = R::dispense('product');
        $product->name     = $name;
        $product->description = $description;
        $product->price    = $price;
        $product->image = $image;
        R::store($product);
    }

    public function findFiltered(?int $categoryId, ?float $minPrice, ?float $maxPrice, ?float $minRating): array
    {
        $conditions = ['1=1'];
        $bindings   = [];

        if ($categoryId !== null) {
            $conditions[] = 'category_id = ?';
            $bindings[]   = $categoryId;
        }
        if ($minPrice !== null) {
            $conditions[] = 'price >= ?';
            $bindings[]   = $minPrice;
        }
        if ($maxPrice !== null) {
            $conditions[] = 'price <= ?';
            $bindings[]   = $maxPrice;
        }
        if ($minRating !== null) {
            $conditions[] = 'rating >= ?';
            $bindings[]   = $minRating;
        }

        $sql = implode(' AND ', $conditions) . ' ORDER BY id ASC';
        return R::find('product', $sql, $bindings);
    }

    public function load(int $id): mixed
    {
        return R::load('product', $id);
    }

    public function save(mixed $bean): void
    {
        R::store($bean);
    }

    public function delete(mixed $bean): void
    {
        R::trash($bean);
    }
}
<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

/**
 * ProductModel
 *
 * Data access layer for the products (toys) resource.
 */

class ProductModel
{
    /**
     * Get all products
     */
    public function getAll(): array
    {
        return R::findAll('product', 'ORDER BY id ASC');
    }

    /**
     * Alias for compatibility
     */
    public function findAll(): array
    {
        return $this->getAll();
    }

    /**
     * Create product
     */
    public function create(array $data): mixed
    {
        $product = R::dispense('product');

        $product->name        = $data['name'] ?? '';
        $product->description = $data['description'] ?? '';
        $product->price       = (float) ($data['price'] ?? 0);
        $product->image       = $data['image'] ?? '';
        $product->stock       = (int) ($data['stock'] ?? 0);
        $product->rating      = (float) ($data['rating'] ?? 0);
        $product->slug        = $data['slug'] ?? '';
        $product->category_id = $data['category_id'] ?? null;
        $product->is_active   = 1;

        R::store($product);

        return $product;
    }

    /**
     * Update product
     */
    public function update(int $id, array $data): mixed
    {
        $product = R::load('product', $id);

        if (!$product || !$product->id) {
            return null;
        }

        if (isset($data['name'])) {
            $product->name = $data['name'];
        }

        if (isset($data['description'])) {
            $product->description = $data['description'];
        }

        if (isset($data['price'])) {
            $product->price = (float) $data['price'];
        }

        if (isset($data['image'])) {
            $product->image = $data['image'];
        }

        if (isset($data['stock'])) {
            $product->stock = (int) $data['stock'];
        }

        if (isset($data['rating'])) {
            $product->rating = (float) $data['rating'];
        }

        if (isset($data['slug'])) {
            $product->slug = $data['slug'];
        }

        if (isset($data['category_id'])) {
            $product->category_id = $data['category_id'];
        }

        R::store($product);

        return $product;
    }

    /**
     * Delete product by ID
     */
    public function delete(int $id): bool
    {
        $product = R::load('product', $id);

        if (!$product || !$product->id) {
            return false;
        }

        R::trash($product);

        return true;
    }

    /**
     * Filtered search
     */
    public function findFiltered(
        ?int $categoryId,
        ?float $minPrice,
        ?float $maxPrice,
        ?float $minRating,
        ?string $search = null
    ): array {
        $conditions = ['1=1'];
        $bindings   = [];

        if ($search) {
            $conditions[] = '(name LIKE ? OR description LIKE ?)';
            $bindings[]   = "%$search%";
            $bindings[]   = "%$search%";
        }

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

    /**
     * Load one product
     */
    public function load(int $id): mixed
    {
        return R::load('product', $id);
    }

    /**
     * Save bean
     */
    public function save(mixed $bean): void
    {
        R::store($bean);
    }
}
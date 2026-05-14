<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class CategoryModel
{
    public function findAll(): array
    {
        return R::findAll('category', 'ORDER BY id DESC');
    }

    public function load(int $id): mixed
    {
        return R::load('category', $id);
    }

    public function create(string $name, string $image): void
    {
        $cat        = R::dispense('category');
        $cat->name  = $name;
        $cat->slug  = strtolower(str_replace(' ', '-', $name));
        $cat->image = $image;
        R::store($cat);
    }

    public function save(mixed $bean): void
    {
        R::store($bean);
    }

    public function delete(mixed $bean): void
    {
        R::trash($bean);
    }

    public function count(): int
    {
        return R::count('category');
    }
}
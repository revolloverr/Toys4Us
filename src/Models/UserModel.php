<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class UserModel
{
    public function findAll(string $search = ''): array
    {
        if ($search) {
            return R::find('user', 'name LIKE ? OR email LIKE ? ORDER BY id DESC', ["%$search%", "%$search%"]);
        }
        return R::findAll('user', 'ORDER BY id DESC');
    }

    public function findByEmail(string $email)
    {
        return R::findOne('user', 'email = ?', [$email]);
    }

    public function create(string $name, string $email, string $password): void
    {
        $user           = R::dispense('user');
        $user->name     = $name;
        $user->email    = $email;
        $user->password = password_hash($password, PASSWORD_BCRYPT);
        $user->role     = 'user';
        R::store($user);
    }

    public function load(int $id): mixed
    {
        return R::load('user', $id);
    }

    public function save(mixed $bean): void
    {
        R::store($bean);
    }

    public function delete(mixed $bean): void
    {
        R::trash($bean);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
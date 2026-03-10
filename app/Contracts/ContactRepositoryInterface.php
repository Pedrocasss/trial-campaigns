<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContactRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): mixed;

    public function findOrFail(int $id): mixed;

    public function unsubscribe(int $id): mixed;
}

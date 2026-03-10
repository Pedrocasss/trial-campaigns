<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContactListRepositoryInterface
{
    public function paginateWithContactsCount(int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): mixed;

    public function findOrFail(int $id): mixed;

    public function addContact(int $listId, int $contactId): void;
}

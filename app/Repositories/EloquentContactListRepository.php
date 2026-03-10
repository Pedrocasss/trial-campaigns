<?php

namespace App\Repositories;

use App\Contracts\ContactListRepositoryInterface;
use App\Models\ContactList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentContactListRepository implements ContactListRepositoryInterface
{
    public function paginateWithContactsCount(int $perPage = 15): LengthAwarePaginator
    {
        return ContactList::withCount('contacts')->paginate($perPage);
    }

    public function create(array $data): ContactList
    {
        return ContactList::create($data);
    }

    public function findOrFail(int $id): ContactList
    {
        return ContactList::findOrFail($id);
    }

    public function addContact(int $listId, int $contactId): void
    {
        $list = $this->findOrFail($listId);
        $list->contacts()->syncWithoutDetaching([$contactId]);
    }
}

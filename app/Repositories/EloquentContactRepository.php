<?php

namespace App\Repositories;

use App\Contracts\ContactRepositoryInterface;
use App\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentContactRepository implements ContactRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Contact::paginate($perPage);
    }

    public function create(array $data): Contact
    {
        return Contact::create($data);
    }

    public function findOrFail(int $id): Contact
    {
        return Contact::findOrFail($id);
    }

    public function unsubscribe(int $id): Contact
    {
        $contact = $this->findOrFail($id);

        if ($contact->status !== 'unsubscribed') {
            $contact->update(['status' => 'unsubscribed']);
        }

        return $contact;
    }
}

<?php

namespace App\Http\Controllers;

use App\Contracts\ContactListRepositoryInterface;
use App\Http\Requests\AddContactToListRequest;
use App\Http\Requests\StoreContactListRequest;
use App\Models\ContactList;
use Illuminate\Http\JsonResponse;

class ContactListController extends Controller
{
    public function __construct(
        private readonly ContactListRepositoryInterface $contactLists
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->contactLists->paginateWithContactsCount());
    }

    public function store(StoreContactListRequest $request): JsonResponse
    {
        $list = $this->contactLists->create($request->validated());

        return response()->json($list, 201);
    }

    public function addContact(AddContactToListRequest $request, ContactList $contactList): JsonResponse
    {
        $this->contactLists->addContact($contactList->id, $request->validated('contact_id'));

        return response()->json(['message' => 'Contact added to list.']);
    }
}

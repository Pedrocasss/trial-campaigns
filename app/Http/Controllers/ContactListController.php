<?php

namespace App\Http\Controllers;

use App\Contracts\ContactListRepositoryInterface;
use App\Http\Requests\AddContactToListRequest;
use App\Http\Requests\StoreContactListRequest;
use App\Http\Resources\ContactListResource;
use App\Models\ContactList;
use Illuminate\Http\JsonResponse;

class ContactListController extends Controller
{
    public function __construct(
        private readonly ContactListRepositoryInterface $contactLists
    ) {}

    public function index()
    {
        return ContactListResource::collection($this->contactLists->paginateWithContactsCount());
    }

    public function store(StoreContactListRequest $request): JsonResponse
    {
        $list = $this->contactLists->create($request->validated());

        return (new ContactListResource($list))
            ->response()
            ->setStatusCode(201);
    }

    public function addContact(AddContactToListRequest $request, ContactList $contactList): JsonResponse
    {
        $this->contactLists->addContact($contactList->id, $request->validated('contact_id'));

        return response()->json(['message' => 'Contact added to list.']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddContactToListRequest;
use App\Http\Requests\StoreContactListRequest;
use App\Models\ContactList;
use Illuminate\Http\JsonResponse;

class ContactListController extends Controller
{
    public function index(): JsonResponse
    {
        $lists = ContactList::withCount('contacts')->paginate(15);

        return response()->json($lists);
    }

    public function store(StoreContactListRequest $request): JsonResponse
    {
        $list = ContactList::create($request->validated());

        return response()->json($list, 201);
    }

    public function addContact(AddContactToListRequest $request, ContactList $contactList): JsonResponse
    {
        $contactList->contacts()->syncWithoutDetaching([$request->validated('contact_id')]);

        return response()->json(['message' => 'Contact added to list.']);
    }
}

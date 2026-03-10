<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function index(): JsonResponse
    {
        $contacts = Contact::paginate(15);

        return response()->json($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = Contact::create($request->validated());

        return response()->json($contact, 201);
    }

    public function unsubscribe(Contact $contact): JsonResponse
    {
        $contact->update(['status' => 'unsubscribed']);

        return response()->json($contact);
    }
}

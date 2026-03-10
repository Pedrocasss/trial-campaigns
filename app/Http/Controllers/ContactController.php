<?php

namespace App\Http\Controllers;

use App\Contracts\ContactRepositoryInterface;
use App\Http\Requests\StoreContactRequest;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->contacts->paginate());
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->contacts->create($request->validated());

        return response()->json($contact, 201);
    }

    public function unsubscribe(Contact $contact): JsonResponse
    {
        $contact = $this->contacts->unsubscribe($contact->id);

        return response()->json($contact);
    }
}

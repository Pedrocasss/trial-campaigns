<?php

namespace App\Http\Controllers;

use App\Contracts\ContactRepositoryInterface;
use App\Http\Requests\StoreContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts
    ) {}

    public function index()
    {
        return ContactResource::collection($this->contacts->paginate());
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->contacts->create($request->validated());

        return (new ContactResource($contact))
            ->response()
            ->setStatusCode(201);
    }

    public function unsubscribe(Contact $contact): ContactResource
    {
        $contact = $this->contacts->unsubscribe($contact->id);

        return new ContactResource($contact);
    }
}

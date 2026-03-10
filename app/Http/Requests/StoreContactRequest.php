<?php

namespace App\Http\Requests;

use App\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:contacts,email'],
            'status' => ['sometimes', 'string', Rule::enum(ContactStatus::class)],
        ];
    }
}

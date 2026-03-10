<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'contact_list_id' => ['required', 'integer', 'exists:contact_lists,id'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}

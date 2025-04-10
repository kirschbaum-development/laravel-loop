<?php

namespace Kirschbaum\Loop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string',
            'messages' => 'sometimes|array',
            'messages.*.user' => 'required|string|in:AI,User',
            'messages.*.message' => 'required|string',
        ];
    }
}

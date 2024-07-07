<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnswerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'text' => 'required|string|max:255',
            'correct' => 'required|integer|min:0|max:1',
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'The Answer Text is required.',
            'text.string' => 'The Answer Text must be a string.',
            'text.max' => 'The Answer Text may not be greater than 255 characters.',
        ];
    }
}

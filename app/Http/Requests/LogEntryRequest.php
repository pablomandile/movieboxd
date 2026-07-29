<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'loggable_type' => ['required', Rule::in(['title', 'season', 'episode'])],
            'loggable_id' => ['required', 'integer'],
            'watched_on' => ['required', 'date', 'before_or_equal:today'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:10'],
            'liked' => ['boolean'],
            'is_rewatch' => ['boolean'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'review' => ['nullable', 'string', 'max:20000'],
            'contains_spoilers' => ['boolean'],
        ];
    }
}

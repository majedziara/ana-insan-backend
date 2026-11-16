<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Rules\PlainText;

class SaveNewsItemRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->route('newsItem') ? 'sometimes' : 'required';

        return [
            'text' => [$required, 'string', 'max:1000', new PlainText],
            'text_en' => ['sometimes', 'nullable', 'string', 'max:1000', new PlainText],
            'duration_days' => [$required, 'integer', 'min:1', 'max:7'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['prohibited'],
            'expires_at' => ['prohibited'],
        ];
    }
}

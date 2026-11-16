<?php

namespace App\Http\Requests\Admin;

use App\Enum\MediaVisibilityEnum;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreCaseMediaRequest extends ApiRequest
{
    public function rules(): array
    {
        $public = $this->input('visibility') === MediaVisibilityEnum::PUBLIC->value;
        $types = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm'];
        if (! $public) {
            $types[] = 'pdf';
        }

        return [
            'file' => ['required', File::types($types)->max(config('anainsan.content.video_max_kb'))],
            'visibility' => ['required', Rule::enum(MediaVisibilityEnum::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}

<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $english = app()->getLocale() === 'en' && filled($this->text_en);

        return ['id' => $this->id, 'text' => $english ? $this->text_en : $this->text, 'locale' => $english ? 'en' : config('anainsan.default_locale'), 'expires_at' => $this->expires_at->toISOString()];
    }
}

<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'text' => $this->text, 'text_en' => $this->text_en, 'duration_days' => $this->duration_days, 'is_active' => $this->is_active, 'published_at' => $this->published_at->toISOString(), 'expires_at' => $this->expires_at->toISOString()];
    }
}

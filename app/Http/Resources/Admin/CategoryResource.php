<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return   [
            'id'   => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'icon' => $this->icon,
            'icon_url' => $this->icon
                ? asset('storage/uploads/categories/' . $this->icon)
                : null,
            'is_active'=>$this->is_active,
            'sort_order'=>$this->sort_order,
        ];
    }
}

<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait LocalizedResponse
{
    /**
     * Returns the resolved language: 'ar', 'en', or null (both).
     */
    protected function lang(): ?string
    {
        return request()->attributes->get('x_lang');
    }

    /**
     * Given an Eloquent collection/item, map name/name_en to a single 'name'
     * based on the requested language.
     *
     * null  → keep both name + name_en as-is
     * 'ar'  → keep only name, drop name_en
     * 'en'  → rename name_en → name, drop original name
     */
    protected function localizeCollection($items, array $nameFields = ['name']): mixed
    {
        $lang = $this->lang();
        if ($lang === null) return $items;

        return $items->map(fn($item) => $this->localizeItem($item, $nameFields));
    }

    protected function localizeItem($item, array $nameFields = ['name']): mixed
    {
        $lang = $this->lang();
        if ($lang === null) return $item;

        $data = is_object($item) && method_exists($item, 'toArray')
            ? $item->toArray()
            : (array) $item;

        foreach ($nameFields as $field) {
            $enField = $field . '_en';
            if ($lang === 'en' && array_key_exists($enField, $data)) {
                $data[$field] = $data[$enField] ?? $data[$field];
            }
            unset($data[$enField]);
        }

        // localize nested arrays recursively (cities, models, sub_sections, etc.)
        foreach ($data as $key => $value) {
            if (is_array($value) && !empty($value) && isset($value[0]) && is_array($value[0])) {
                $data[$key] = array_map(function ($nested) use ($nameFields, $lang) {
                    foreach ($nameFields as $field) {
                        $enField = $field . '_en';
                        if ($lang === 'en' && array_key_exists($enField, $nested)) {
                            $nested[$field] = $nested[$enField] ?? $nested[$field];
                        }
                        unset($nested[$enField]);
                    }
                    return $nested;
                }, $value);
            }
        }

        return $data;
    }

    /**
     * Localize a flat key=>value map (like system settings).
     * 'ar' → remove all _en keys
     * 'en' → replace ar value with en value where available, remove _en keys
     * null → keep everything
     */
    protected function localizeSettingsMap(array $map, array $bilingualKeys): array
    {
        $lang = $this->lang();
        if ($lang === null) return $map;

        foreach ($bilingualKeys as $key) {
            $enKey = $key . '_en';
            if ($lang === 'en' && isset($map[$enKey]) && $map[$enKey] !== null) {
                $map[$key] = $map[$enKey];
            }
            unset($map[$enKey]);
        }

        return $map;
    }

    /**
     * Localize options array on category fields.
     * 'ar' → return options only
     * 'en' → return options_en (fallback to options if en is empty)
     * null → return both
     */
    protected function localizeFieldOptions(array $field): array
    {
        $lang = $this->lang();
        if ($lang === null) return $field;

        if ($lang === 'en') {
            $field['options'] = !empty($field['options_en']) ? $field['options_en'] : $field['options'];
            $field['display_name'] = !empty($field['display_name_en']) ? $field['display_name_en'] : $field['display_name'];
        }

        unset($field['options_en'], $field['display_name_en']);

        return $field;
    }
}

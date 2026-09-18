<?php

namespace App\Traits;

use App\Models\Url;
use Illuminate\Support\Str;

trait GeneratesShortCode
{
    private function generateShortCode(): string
    {
        do {
            $code = Str::random(8);
        } while (Url::query()->where('short_code', $code)->exists());

        return $code;
    }
}

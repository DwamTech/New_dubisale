<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLanguage
{
    public function handle(Request $request, Closure $next)
    {
        $lang = strtolower(trim($request->header('x-lang', '')));
        $resolved = in_array($lang, ['ar', 'en']) ? $lang : null;

        $request->attributes->set('x_lang', $resolved);

        // Set Laravel locale so __() / trans() work automatically
        if ($resolved) {
            app()->setLocale($resolved);
        }

        return $next($request);
    }
}

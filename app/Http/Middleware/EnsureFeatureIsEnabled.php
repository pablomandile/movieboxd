<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureIsEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Setting::enabled("features.{$feature}"), 403, __('app.feature_disabled'));

        return $next($request);
    }
}

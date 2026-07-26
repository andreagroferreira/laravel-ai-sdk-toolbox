<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Http\Middleware;

use AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeAiToolbox
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ai-sdk-toolbox.http.enabled', false)) {
            abort(404);
        }

        if (! AiSdkToolbox::check($request->user())) {
            abort(403);
        }

        return $next($request);
    }
}

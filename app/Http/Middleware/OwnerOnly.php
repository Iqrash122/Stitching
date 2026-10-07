<?php

namespace App\Http\Middleware;

use App\Policies\StudioPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && (new StudioPolicy)->access($request->user()), 403);

        return $next($request);
    }
}

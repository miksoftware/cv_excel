<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AccessPassword
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('access_granted')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado Y sea de tipo staff
        if (! auth()->check() || auth()->user()->user_type !== 'staff') {
            abort(403, 'No tienes acceso al panel administrativo. Solo personal autorizado.');
        }

        return $next($request);
    }
}

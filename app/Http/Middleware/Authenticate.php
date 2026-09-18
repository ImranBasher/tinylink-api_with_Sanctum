<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function unauthenticated($request, array $guards): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401));
    }
}
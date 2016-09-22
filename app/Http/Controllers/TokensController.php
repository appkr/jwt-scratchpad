<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

class TokensController extends Controller
{
    public function store(Request $request)
    {
        $token = jwt()->attempt(
            $request->only('email', 'password')
        );

        return response()->json(
            compact('token'),
            201,
            [],
            JSON_PRETTY_PRINT
        );
    }
}

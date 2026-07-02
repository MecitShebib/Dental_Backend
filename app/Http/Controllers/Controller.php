<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}

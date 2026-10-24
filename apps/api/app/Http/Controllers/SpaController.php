<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpaController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $spa = public_path('spa.html');

        abort_unless(is_file($spa), 404);

        return response()->file($spa);
    }
}

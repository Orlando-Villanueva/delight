<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AndroidReleaseController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'version' => (string) config('android_release.version'),
                'version_code' => (int) config('android_release.version_code'),
                'update_url' => (string) config('android_release.update_url'),
            ],
        ]);
    }
}

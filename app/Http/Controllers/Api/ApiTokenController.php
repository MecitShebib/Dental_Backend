<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiToken\StoreApiTokenRequest;
use App\Http\Resources\ApiTokenResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lets an authenticated user issue long-lived Sanctum tokens for outside
 * integrations (a third-party system calling the API directly -- e.g. an
 * X-ray imaging machine posting images to a client's chart) from
 * Settings > API Token, separate from the short-lived-in-spirit tokens
 * AuthController hands out at OTP login. Both are the same underlying
 * Sanctum personal_access_tokens row; the ApiTokenResource::PREFIX on the
 * `name` column is what tells the two apart.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        $tokens = $request->user()->tokens()
            ->where('name', 'like', ApiTokenResource::PREFIX.'%')
            ->latest()
            ->get();

        return $this->success(ApiTokenResource::collection($tokens));
    }

    public function store(StoreApiTokenRequest $request)
    {
        $newAccessToken = $request->user()->createToken(
            ApiTokenResource::PREFIX.$request->validated('name'),
            ['*'],
        );

        return $this->success([
            'token' => ApiTokenResource::make($newAccessToken->accessToken),
            'plain_text_token' => $newAccessToken->plainTextToken,
        ], 'API token created successfully.', 201);
    }

    public function destroy(Request $request, string $tokenId)
    {
        $token = $request->user()->tokens()
            ->where('name', 'like', ApiTokenResource::PREFIX.'%')
            ->where('id', $tokenId)
            ->first();

        if (! $token) {
            throw ValidationException::withMessages([
                'token' => ['This API token does not exist.'],
            ]);
        }

        $token->delete();

        return $this->success(null, 'API token revoked successfully.');
    }
}

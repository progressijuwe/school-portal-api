<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        /*
         * Signing in no longer revokes the account's other tokens.
         *
         * It used to, which made this a single-session system by accident: a
         * lecturer signing in on their phone was silently logged out on the
         * laptop they had a mark sheet open in. Tokens carry an expiry
         * (config/sanctum.php, seven days by default) and `sanctum:prune-expired`
         * runs daily, so they do not accumulate indefinitely.
         *
         * Deliberate revocation still happens where it protects the account:
         * changing a password and an administrator resetting one both drop
         * every existing token, so a compromised session cannot outlive the
         * credentials it was opened with.
         */
        $token = $user->createToken(
            $request->header('User-Agent', 'unknown-client')
        )->plainTextToken;

        if ($user->department_id) {
            $user->load('department.faculty');
        }

        if ($user->hasRole('lecturer')) {
            $user->load('lecturerProfile');
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}

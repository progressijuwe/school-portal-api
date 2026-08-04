<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            // Someone who has just set their own password is no longer waiting
            // on an administrator, whatever they submitted earlier.
            'password_reset_requested_at' => null,
        ]);

        // Revoke all tokens and force re-login with new password
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Please log in again.',
        ]);
    }

    /**
     * Flag an account as locked out so an administrator can reset it.
     *
     * This is deliberately not Laravel's emailed reset-link flow. No mail
     * service is configured, so a link would be generated and never delivered —
     * worse than not offering one, because the user would wait for an email
     * that cannot arrive. Instead the request is recorded against the user and
     * surfaced to admins, who issue a new temporary password and hand it over
     * through whatever channel the school already uses.
     *
     * The response is identical whether or not the address matches an account.
     * Answering "no account found" would let anyone test which addresses are
     * registered, and those addresses are students and staff.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        // Repeated submissions only refresh the timestamp, so a user pressing
        // the button twice cannot stack up duplicate work for the admin.
        $user?->forceFill(['password_reset_requested_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => 'If that email matches an account, your administrator has been notified and will issue you a new password.',
        ]);
    }
}

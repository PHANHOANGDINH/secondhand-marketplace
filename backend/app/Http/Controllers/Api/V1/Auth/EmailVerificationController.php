<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\VerifyEmailCodeRequest;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, EmailVerificationService $verification, int $id, string $hash): JsonResponse
    {
        if (! $request->hasValidRelativeSignature() || ! $verification->verifyLink($id, $hash)) {
            return response()->json(['message' => 'This verification link is invalid or expired.', 'code' => 'EMAIL_VERIFICATION_LINK_INVALID'], 422);
        }

        return response()->json(['message' => 'Email verified. Please sign in.']);
    }

    public function verifyCode(VerifyEmailCodeRequest $request, EmailVerificationService $verification): JsonResponse
    {
        $data = $request->validated();
        if (! $verification->verifyCode($data['email'], $data['code'])) {
            return response()->json(['message' => 'This verification code is invalid or expired.', 'code' => 'EMAIL_VERIFICATION_CODE_INVALID'], 422);
        }

        return response()->json(['message' => 'Email verified. Please sign in.']);
    }

    /** The answer is the same whether or not the account exists or still needs verification. */
    public function resend(ForgotPasswordRequest $request, EmailVerificationService $verification): JsonResponse
    {
        $verification->resend($request->safe()->string('email')->toString());

        return response()->json(['message' => 'If this account is waiting for verification, a new link has been sent.']);
    }
}

<?php

namespace App\Http\Requests\Domain;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class ZaloPayCallbackRequest extends DomainRequest
{
    /** ZaloPay expects HTTP 200 with a return_code even for a malformed notification. */
    protected function failedValidation(Validator $validator): void
    {
        Log::warning('zalopay.callback', ['result' => -1, 'reason' => 'malformed', 'fields' => array_keys($validator->failed())]);

        throw new HttpResponseException(response()->json(['return_code' => -1, 'return_message' => 'Invalid payment']));
    }

    public function rules(): array
    {
        return ['data' => ['required', 'string', 'max:20000'], 'mac' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/D'], 'type' => ['required', 'integer', 'in:1']];
    }
}

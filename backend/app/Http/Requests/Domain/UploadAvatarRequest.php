<?php

namespace App\Http\Requests\Domain;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 2 MB is also PHP's upload limit here; the page shrinks pictures to well under it before sending.
        return ['avatar' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048']];
    }
}

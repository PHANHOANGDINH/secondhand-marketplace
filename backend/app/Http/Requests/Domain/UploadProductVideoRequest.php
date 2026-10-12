<?php

namespace App\Http\Requests\Domain;

class UploadProductVideoRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return ['video' => ['bail', 'required', 'file', 'mimetypes:video/mp4,video/webm', 'extensions:mp4,webm', 'max:'.config('product-videos.max_kilobytes')], 'title' => ['nullable', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:1000']];
    }
}

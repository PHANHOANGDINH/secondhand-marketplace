<?php

namespace App\Http\Requests\Domain;

class UploadReviewVideoRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('review'));
    }

    public function rules(): array
    {
        return ['video' => ['bail', 'required', 'file', 'mimetypes:video/mp4,video/webm', 'extensions:mp4,webm', 'max:'.config('review-videos.max_kilobytes')]];
    }
}

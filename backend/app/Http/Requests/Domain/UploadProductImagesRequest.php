<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Validator;

class UploadProductImagesRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return ['images' => ['required', 'array', 'min:1', 'max:8'], 'images.*' => ['bail', 'required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120']];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->file('images', []) as $index => $file) {
                $size = @getimagesize($file->getRealPath());
                if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 20000000) {
                    $validator->errors()->add('images.'.$index, 'An image must contain at most 20 megapixels.');
                }
            }
        }];
    }
}

<?php

namespace App\Http\Requests\Domain;

class NotificationListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'filter' => ['sometimes', 'in:all,unread,auction,orders']];
    }
}

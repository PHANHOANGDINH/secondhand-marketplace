<?php

namespace App\Enums;

enum VideoCommentStatus: string
{
    case Published = 'PUBLISHED';
    case Hidden = 'HIDDEN';
}

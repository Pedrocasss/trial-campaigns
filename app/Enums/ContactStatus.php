<?php

namespace App\Enums;

enum ContactStatus: string
{
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';
}

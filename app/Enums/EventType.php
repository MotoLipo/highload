<?php

namespace App\Enums;

enum EventType: string
{
    case PageView = 'page_view';
    case Purchase = 'purchase';
    case SignUp = 'signup';
}
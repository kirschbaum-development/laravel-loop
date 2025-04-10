<?php

namespace Kirschbaum\Loop\Enums;

enum MessageType: string
{
    case REQUEST = 'request';
    case NOTIFICATION = 'notification';
    case RESPONSE = 'response';
    case ERROR = 'error';
}
<?php

namespace Michalsn\CodeIgniterQueue\Enums;

enum Status: int
{
    case PENDING = 0;
    case RESERVED = 1;
    case DONE = 2;
}

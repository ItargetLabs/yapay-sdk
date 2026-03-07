<?php

declare(strict_types=1);

namespace YapaySdk;

enum PaymentStatus: string
{
    case APPROVED = 'approved';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
    case FAILED = 'failed';
}

<?php

namespace App\Exception;

use App\Enum\ProductStatus;

class InvalidStatusTransitionException extends \RuntimeException
{
    public function __construct(ProductStatus $from, ProductStatus $to)
    {
        parent::__construct(sprintf(
            'Cannot transition product status from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }
}

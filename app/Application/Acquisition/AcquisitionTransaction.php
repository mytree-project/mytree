<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

interface AcquisitionTransaction
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed;
}

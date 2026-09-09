<?php

declare(strict_types=1);

namespace App\Infrastructure\Acquisition;

use App\Application\Acquisition\AcquisitionTransaction;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

final class LaravelAcquisitionTransaction implements AcquisitionTransaction
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        return DB::transaction(
            static fn (Connection $_connection) => $operation(),
        );
    }
}

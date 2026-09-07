<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition\Models;

use Illuminate\Database\Eloquent\Model;

final class SourceLocatorRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'source_locators';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'source_id',
        'claim_id',
        'source_asset_id',
        'schema_version',
        'locator_type',
        'value_payload',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['schema_version' => 'integer'];
    }
}

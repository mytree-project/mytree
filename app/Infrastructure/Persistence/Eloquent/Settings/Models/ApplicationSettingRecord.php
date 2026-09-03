<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Settings\Models;

use Illuminate\Database\Eloquent\Model;

final class ApplicationSettingRecord extends Model
{
    protected $table = 'application_settings';

    /** @var list<string> */
    protected $fillable = [
        'section',
        'key',
        'value_type',
        'schema_version',
        'value',
        'revision',
        'value_hash',
        'changed_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'revision' => 'integer',
        ];
    }
}

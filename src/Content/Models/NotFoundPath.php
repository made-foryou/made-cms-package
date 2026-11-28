<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\NotFoundPathBuilder;
use Made\CmsPackage\Database\Factories\Content\NotFoundPathFactory;

/**
 * An address where visitors found no page: a dead door, until a page or a redirect gets it or someone ignores it.
 *
 * @property int $id
 * @property string $path the address without the prefix of a route group, as a redirect holds it
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $ignored_at
 *
 * @method static NotFoundPathBuilder query()
 */
class NotFoundPath extends Model
{
    /** @use HasFactory<NotFoundPathFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'cms_not_found_paths';

    protected $fillable = [
        'path',
        'first_seen_at',
        'last_seen_at',
        'ignored_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'ignored_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): NotFoundPathBuilder
    {
        return new NotFoundPathBuilder($query);
    }

    protected static function newFactory(): NotFoundPathFactory
    {
        return NotFoundPathFactory::new();
    }
}

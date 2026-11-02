<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\RouteBuilder;
use Made\CmsPackage\Database\Factories\Content\RouteFactory;

/**
 * An address of the site and the content that lives there.
 *
 * @property int $id
 * @property string $path the address, normalised: "/" for the home page, "/over-ons/team" for a nested one
 * @property string $routable_type
 * @property int $routable_id
 * @property-read Model|null $routable
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static RouteBuilder query()
 */
class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use HasFactory;

    protected $table = 'cms_routes';

    protected $fillable = [
        'path',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function routable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): RouteBuilder
    {
        return new RouteBuilder($query);
    }

    protected static function newFactory(): RouteFactory
    {
        return RouteFactory::new();
    }
}

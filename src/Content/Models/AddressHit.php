<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\AddressHitBuilder;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Database\Factories\Content\AddressHitFactory;

/**
 * How often an address was asked for on a day, with one outcome from one source: a count, never a visit.
 *
 * @property int $id
 * @property CarbonImmutable $day the day in the timezone of the app
 * @property string $prefix the prefix of the route group without slashes, "" at the root
 * @property string $path the address within the group
 * @property HitOutcome $outcome
 * @property VisitSource $source
 * @property string $referrer_path the address of the page of the site that linked here, "" for any other source
 * @property int $hits
 *
 * @method static AddressHitBuilder query()
 */
class AddressHit extends Model
{
    /** @use HasFactory<AddressHitFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'cms_address_hits';

    /**
     * The day is a date: stored without a time, so it compares with a date on every database.
     */
    protected $dateFormat = 'Y-m-d';

    protected $fillable = [
        'day',
        'prefix',
        'path',
        'outcome',
        'source',
        'referrer_path',
        'hits',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'outcome' => HitOutcome::class,
            'source' => VisitSource::class,
            'hits' => 'integer',
        ];
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): AddressHitBuilder
    {
        return new AddressHitBuilder($query);
    }

    protected static function newFactory(): AddressHitFactory
    {
        return AddressHitFactory::new();
    }
}

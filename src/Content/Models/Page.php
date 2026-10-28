<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\PageBuilder;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Database\Factories\Content\PageFactory;

/**
 * A page of the site that editors manage: its path on the site and the strips it is built from.
 *
 * @property int $id
 * @property string $title
 * @property string $path
 * @property PageStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Strip> $strips
 *
 * @method static PageBuilder query()
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected $table = 'cms_pages';

    protected $fillable = [
        'title',
        'path',
        'status',
        'published_at',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * The strips of the page, in the order they show.
     *
     * @return HasMany<Strip, $this>
     */
    public function strips(): HasMany
    {
        return $this->hasMany(Strip::class)->orderBy('position');
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): PageBuilder
    {
        return new PageBuilder($query);
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }
}

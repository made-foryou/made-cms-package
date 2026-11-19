<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\PageBuilder;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Database\Factories\Content\PageFactory;

/**
 * A page of the site that editors manage.
 *
 * @phpstan-import-type StoredStrip from Strip
 *
 * @property int $id
 * @property int|null $parent_id the page this page lies under; null for a page at the top
 * @property string $title
 * @property string $slug the last segment of the address
 * @property int $position the place among the pages with the same parent, 0 for the first
 * @property bool $in_menu
 * @property bool $hidden whether visitors only reach the page through its address, outside the menu, the sitemap and Google
 * @property string|null $meta_title the whole title in Google; null gives the name of the page with the site name
 * @property string|null $meta_description the description in Google; null lets Google take a piece of the page
 * @property bool $in_sitemap whether the sitemap of the site lists the page
 * @property bool $indexable whether Google may show the page; off is noindex
 * @property PageStatus $status
 * @property list<StoredStrip> $strips the published strips, in order
 * @property list<StoredStrip>|null $draft_strips the draft editors work on; null when there is none
 * @property CarbonImmutable|null $published_at
 * @property-read Route|null $route the address of the page on the site
 * @property-read Page|null $parent
 * @property-read Collection<int, Page> $children
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at when the page was deleted; it can be restored until it is pruned
 *
 * @method static PageBuilder query()
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'cms_pages';

    protected $fillable = [
        'parent_id',
        'title',
        'slug',
        'position',
        'in_menu',
        'hidden',
        'meta_title',
        'meta_description',
        'in_sitemap',
        'indexable',
        'status',
        'strips',
        'draft_strips',
        'published_at',
    ];

    protected $attributes = [
        'status' => 'draft',
        'strips' => '[]',
        'position' => 0,
        'in_menu' => true,
        'hidden' => false,
        'in_sitemap' => true,
        'indexable' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'position' => 'integer',
            'in_menu' => 'boolean',
            'hidden' => 'boolean',
            'in_sitemap' => 'boolean',
            'indexable' => 'boolean',
            'status' => PageStatus::class,
            'strips' => 'array',
            'draft_strips' => 'array',
            'published_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphOne<Route, $this>
     */
    public function route(): MorphOne
    {
        return $this->morphOne(Route::class, 'routable');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id');
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

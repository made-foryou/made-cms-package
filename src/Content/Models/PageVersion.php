<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\PageVersionBuilder;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Database\Factories\Content\PageVersionFactory;

/**
 * One publication of a page: the strips that went live and what they changed.
 *
 * @phpstan-import-type StoredStrip from Strip
 *
 * @property int $id
 * @property int $page_id
 * @property int $number 1 for the first publication the page keeps, counting on per page
 * @property list<StoredStrip> $strips the strips that went live, in order
 * @property array<string, mixed> $changes what the publication changed (VersionChanges)
 * @property int|null $published_by the editor who published it; null when that is not known
 * @property CarbonImmutable $published_at
 * @property-read Page|null $page
 *
 * @method static PageVersionBuilder query()
 */
class PageVersion extends Model
{
    /** @use HasFactory<PageVersionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'cms_page_versions';

    protected $fillable = [
        'page_id',
        'number',
        'strips',
        'changes',
        'published_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_id' => 'integer',
            'number' => 'integer',
            'strips' => 'array',
            'changes' => 'array',
            'published_by' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): PageVersionBuilder
    {
        return new PageVersionBuilder($query);
    }

    protected static function newFactory(): PageVersionFactory
    {
        return PageVersionFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\StripBuilder;
use Made\CmsPackage\Database\Factories\Content\StripFactory;

/**
 * One strip of a page: a strip type of the site with its content. Serialises to {id, type, content},
 * so a host passes $page->strips straight to Blade, Inertia or a JSON response.
 *
 * @property int $id
 * @property int $page_id
 * @property string $type
 * @property int $position
 * @property array<string, mixed> $content
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Page $page
 *
 * @method static StripBuilder query()
 */
class Strip extends Model
{
    /** @use HasFactory<StripFactory> */
    use HasFactory;

    protected $table = 'cms_strips';

    protected $fillable = [
        'page_id',
        'type',
        'position',
        'content',
    ];

    protected $hidden = [
        'page_id',
        'position',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'content' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): StripBuilder
    {
        return new StripBuilder($query);
    }

    protected static function newFactory(): StripFactory
    {
        return StripFactory::new();
    }
}

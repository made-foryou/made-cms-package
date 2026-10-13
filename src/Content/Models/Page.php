<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\PageBuilder;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Database\Factories\Content\PageFactory;

/**
 * A page of the site that editors manage.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property PageStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
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
        'slug',
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
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder($query): PageBuilder
    {
        return new PageBuilder($query);
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }
}

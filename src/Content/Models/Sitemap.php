<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\SitemapBuilder;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Database\Factories\Content\SitemapFactory;

/**
 * A built sitemap of a route group of the site, as it is served at its sitemap.xml.
 *
 * @property int $id
 * @property string $prefix the prefix of the route group without slashes, "" at the root
 * @property string $xml
 * @property int $address_count
 * @property list<array{path: string|null, label: string, reason: string}> $errors
 * @property SitemapCause $cause
 * @property int|null $page_id the page whose change led to it
 * @property int|null $editor_id the editor who built it by hand
 * @property CarbonImmutable $built_at
 *
 * @method static SitemapBuilder query()
 */
class Sitemap extends Model
{
    /** @use HasFactory<SitemapFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'cms_sitemaps';

    protected $fillable = [
        'prefix',
        'xml',
        'address_count',
        'errors',
        'cause',
        'page_id',
        'editor_id',
        'built_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address_count' => 'integer',
            'errors' => 'array',
            'cause' => SitemapCause::class,
            'page_id' => 'integer',
            'editor_id' => 'integer',
            'built_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): SitemapBuilder
    {
        return new SitemapBuilder($query);
    }

    protected static function newFactory(): SitemapFactory
    {
        return SitemapFactory::new();
    }
}

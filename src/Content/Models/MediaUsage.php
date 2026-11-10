<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\MediaUsageBuilder;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Database\Factories\Content\MediaUsageFactory;

/**
 * One place a file of the media library is used: a field of a strip in the draft or the published version of a page.
 *
 * @property int $id
 * @property int $media_id
 * @property int $page_id
 * @property UsageVersion $version
 * @property string $strip_id
 * @property string $strip_type the key of the strip type; it may no longer be in the catalogue
 * @property string $field the path of the field in the strip: "foto", or "kaarten.2.foto" in an item of a list
 * @property-read Media|null $media
 * @property-read Page|null $page
 *
 * @method static MediaUsageBuilder query()
 */
class MediaUsage extends Model
{
    /** @use HasFactory<MediaUsageFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'cms_media_usages';

    protected $fillable = [
        'media_id',
        'page_id',
        'version',
        'strip_id',
        'strip_type',
        'field',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_id' => 'integer',
            'page_id' => 'integer',
            'version' => UsageVersion::class,
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
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
    public function newEloquentBuilder(mixed $query): MediaUsageBuilder
    {
        return new MediaUsageBuilder($query);
    }

    protected static function newFactory(): MediaUsageFactory
    {
        return MediaUsageFactory::new();
    }
}

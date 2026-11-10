<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\MediaFolderBuilder;
use Made\CmsPackage\Database\Factories\Content\MediaFolderFactory;

/**
 * A folder of the media library. Folders are one level deep: they never hold other folders.
 *
 * @property int $id
 * @property string $name unique
 * @property-read Collection<int, Media> $media
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static MediaFolderBuilder query()
 */
class MediaFolder extends Model
{
    /** @use HasFactory<MediaFolderFactory> */
    use HasFactory;

    protected $table = 'cms_media_folders';

    protected $fillable = ['name'];

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): MediaFolderBuilder
    {
        return new MediaFolderBuilder($query);
    }

    protected static function newFactory(): MediaFolderFactory
    {
        return MediaFolderFactory::new();
    }
}

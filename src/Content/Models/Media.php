<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\MediaBuilder;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Database\Factories\Content\MediaFactory;

/**
 * A file of the media library: a photo, a document or a video, stored on the media disk.
 *
 * @property int $id
 * @property int|null $folder_id the folder the file is in; null for none
 * @property MediaKind $kind
 * @property string $name the slug of the file name without extension
 * @property string $path the file the site shows, on the media disk
 * @property string|null $original_path the upload as it came in, next to a photo that was scaled down
 * @property string $mime the type of the file at path
 * @property int $size the bytes of the file at path
 * @property int|null $width in pixels, for an image whose size is known
 * @property int|null $height in pixels, for an image whose size is known
 * @property string|null $alt the alt text of an image, the description of the download link of a document
 * @property float|null $focal_x the point a crop keeps in view, from 0 (left) to 1 (right)
 * @property float|null $focal_y the point a crop keeps in view, from 0 (top) to 1 (bottom)
 * @property int|null $uploaded_by the id of the editor who uploaded the file
 * @property-read MediaFolder|null $folder
 * @property-read Collection<int, MediaUsage> $usages
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static MediaBuilder query()
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'cms_media';

    protected $fillable = [
        'folder_id',
        'kind',
        'name',
        'path',
        'original_path',
        'mime',
        'size',
        'width',
        'height',
        'alt',
        'focal_x',
        'focal_y',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'folder_id' => 'integer',
            'kind' => MediaKind::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'focal_x' => 'float',
            'focal_y' => 'float',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * The places the file is used, in the draft and the published version of every page, deleted pages included.
     *
     * @return HasMany<MediaUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class, 'media_id');
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): MediaBuilder
    {
        return new MediaBuilder($query);
    }

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }
}

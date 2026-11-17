<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

/**
 * Wraps the adapter of a disk and keeps, per path, the options a write or a copy handed it and a visibility set later:
 * what an S3 disk turns into the ACL and the Cache-Control header of an object, which a local disk cannot show (it
 * ignores the header, and on Windows a file cannot be private). The options are those of the disk with the ones of
 * the write on top, as the adapter gets them. It also keeps every change in order (calls()).
 */
final class RecordingAdapter implements FilesystemAdapter
{
    /** @var array<string, array{visibility: string|null, directory_visibility: string|null, CacheControl: string|null}> */
    private array $written = [];

    /** @var list<string> */
    private array $calls = [];

    public function __construct(
        private readonly FilesystemAdapter $adapter,
    ) {}

    /**
     * The options the last write or copy to this path handed the disk, with a visibility set afterwards on top; null
     * when nothing was written there.
     *
     * @return array{visibility: string|null, directory_visibility: string|null, CacheControl: string|null}|null
     */
    public function written(string $path): ?array
    {
        return $this->written[$path] ?? null;
    }

    /**
     * Every change this disk made, in order: "write {path}", "copy {path}", "visibility {path} {visibility}",
     * "delete {path}" and "delete directory {path}", with what a test noted in between (note()).
     *
     * @return list<string>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * Puts something that happened elsewhere, such as a query, in the order of calls().
     */
    public function note(string $event): void
    {
        $this->calls[] = $event;
    }

    public function fileExists(string $path): bool
    {
        return $this->adapter->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->adapter->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->adapter->write($path, $contents, $config);
        $this->record($path, $config);
        $this->calls[] = "write {$path}";
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->adapter->writeStream($path, $contents, $config);
        $this->record($path, $config);
        $this->calls[] = "write {$path}";
    }

    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->adapter->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->adapter->delete($path);
        $this->calls[] = "delete {$path}";
    }

    public function deleteDirectory(string $path): void
    {
        $this->adapter->deleteDirectory($path);
        $this->calls[] = "delete directory {$path}";
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->adapter->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->adapter->setVisibility($path, $visibility);
        $this->calls[] = "visibility {$path} {$visibility}";
        $this->written[$path] = [...$this->written[$path] ?? ['directory_visibility' => null, 'CacheControl' => null], 'visibility' => $visibility];
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->adapter->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->adapter->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->adapter->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->adapter->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->adapter->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->adapter->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->adapter->copy($source, $destination, $config);
        $this->record($destination, $config);
        $this->calls[] = "copy {$destination}";
    }

    private function record(string $path, Config $config): void
    {
        $this->written[$path] = [
            'visibility' => self::text($config->get(Config::OPTION_VISIBILITY)),
            'directory_visibility' => self::text($config->get(Config::OPTION_DIRECTORY_VISIBILITY)),
            'CacheControl' => self::text($config->get('CacheControl')),
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}

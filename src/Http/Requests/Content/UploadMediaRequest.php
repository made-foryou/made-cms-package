<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Http\UploadedFile;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Http\Cms\UploadField;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * One file from the upload field, with the folder it goes into. Only that it arrived is checked here: what the file
 * is and whether the library takes it is read from its contents by UploadMedia, never from what the browser says.
 */
final class UploadMediaRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'folder' => ['nullable', 'integer'],
        ];
    }

    /**
     * A file php itself refused (upload_max_filesize, a broken connection) never arrived: the server is the limit
     * then, not the library.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.uploaded' => (string) __('made-cms-package::media.errors.server_limit', ['max' => UploadField::serverLimit()]),
            'file.*' => $this->error('file_missing'),
            'folder.*' => $this->error('folder'),
        ];
    }

    /**
     * The upload under the name the editor had it, by the editor who is logged in.
     */
    public function toData(): UploadedMedia
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return new UploadedMedia(
            path: $file->getPathname(),
            filename: $file->getClientOriginalName(),
            folderId: $this->filled('folder') ? $this->integer('folder') : null,
            uploadedBy: Visitor::requireEditor($this)->id,
        );
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}

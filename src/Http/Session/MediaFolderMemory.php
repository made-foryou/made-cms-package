<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Session;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * The folder of the media library an editor chose last in the picker, so the next edit shell opens the picker there
 * and uploads into it. Transport state only: the shell checks the folder still exists (FindMediaFolder).
 *
 * The session is written back as a whole after every request, so a request that started earlier and ends later (a
 * long upload) can write the former folder back. Within an open shell the folder lives in its script; the session
 * only gives the next shell its start.
 */
final readonly class MediaFolderMemory
{
    private const string KEY = 'made-cms-package.media_folder';

    public function __construct(
        private Session $session,
    ) {}

    public static function of(Request $request): self
    {
        return new self($request->session());
    }

    public function id(): ?int
    {
        $id = $this->session->get(self::KEY);

        return is_int($id) ? $id : null;
    }

    /**
     * @param  int|null  $id  the folder; null for "Alle bestanden"
     */
    public function remember(?int $id): void
    {
        if ($id === null) {
            $this->session->forget(self::KEY);

            return;
        }

        $this->session->put(self::KEY, $id);
    }
}

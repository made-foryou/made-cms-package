<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Events\StripAdded;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class AddStrip
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * Places a new strip of a type in the draft, filled with the defaults of the type in their stored form.
     *
     * @param  int  $position  0 places it first, the number of strips places it last
     *
     * @throws UnknownStripType
     * @throws InvalidStripPosition
     */
    public function handle(Page $page, string $type, int $position, ?int $editorId = null): Strip
    {
        $definition = $this->types->find($type);

        return ChangeDraft::run($page, static function (PageStrips $strips, Page $page) use ($definition, $position): Strip {
            if ($position < 0 || $position > $strips->count()) {
                throw InvalidStripPosition::for($page, $position, $strips->count());
            }

            $strip = new Strip((string) Str::uuid(), $definition->key(), true, $definition->normalize($definition->defaults()));

            $page->draft_strips = $strips->insert($strip, $position)->toArray();

            StripAdded::dispatch($page, $strip, $position);

            return $strip;
        }, $editorId);
    }
}

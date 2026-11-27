<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Exceptions\PublicationRefused;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class PutDraftLive
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * The one step that publishes: makes the working copy the published version of the page and keeps it as the next
     * version of the page (RecordPageVersion), with the editor who published it. It runs only inside the change of
     * ChangeDraft (PublishPage, PublishScheduledPage), which hands it the page with its row locked and saves it. A
     * publication takes the planned publication along, and the note of a planned one a strip stopped.
     *
     * Every strip must pass its strip type, hidden strips too, so they never break once they are shown. A stored value
     * is checked in its current form, so a heading stored as text or a level its field no longer offers never stops
     * the publication. Strips of a type that is no longer in the catalogue cannot be checked and are published as
     * they are.
     *
     * @throws PublicationRefused keyed by "strips.<strip id>.<field path>", before anything is written
     */
    public function handle(Page $page, PageStrips $strips, ?int $editorId): void
    {
        $this->check($strips);

        $now = now()->toImmutable();

        RecordPageVersion::run($page, $strips, $now, $editorId);

        $page->strips = $strips->toArray();
        $page->draft_strips = null;
        $page->status = PageStatus::Published;
        $page->published_at = $now;
        $page->publish_at = null;
        $page->scheduled_by = null;
        $page->publish_failed_at = null;

        PagePublished::dispatch($page);
    }

    /**
     * @throws PublicationRefused
     */
    private function check(PageStrips $strips): void
    {
        $errors = [];

        foreach ($strips->strips as $strip) {
            if (! $this->types->has($strip->type)) {
                continue;
            }

            try {
                $type = $this->types->find($strip->type);

                CheckStripValues::run($type, $type->normalize($strip->values));
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $path => $messages) {
                    $errors["strips.{$strip->id}.{$path}"] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw PublicationRefused::withMessages($errors);
        }
    }
}

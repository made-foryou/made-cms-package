<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Exceptions\PageAlreadyPublished;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class PublishPage
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * Makes the draft the published version of the page.
     *
     * Every strip of the draft must pass its strip type, hidden strips too, so they never
     * break once they are shown. Strips of a type that is no longer in the catalogue cannot
     * be checked and are published as they are.
     *
     * @throws PageAlreadyPublished when the page is published and has no draft
     * @throws ValidationException keyed by "strips.<strip id>.<field path>"
     */
    public function handle(Page $page): Page
    {
        if ($page->status === PageStatus::Published && $page->draft_strips === null) {
            throw PageAlreadyPublished::for($page);
        }

        $strips = PageStrips::fromArray($page->draft_strips ?? $page->strips);

        $this->check($strips);

        $page->strips = $strips->toArray();
        $page->draft_strips = null;
        $page->status = PageStatus::Published;
        $page->published_at = now()->toImmutable();
        $page->save();

        PagePublished::dispatch($page);

        return $page;
    }

    /**
     * @throws ValidationException
     */
    private function check(PageStrips $strips): void
    {
        $errors = [];

        foreach ($strips->strips as $strip) {
            if (! $this->types->has($strip->type)) {
                continue;
            }

            try {
                CheckStripValues::run($this->types->find($strip->type), $strip->values);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $path => $messages) {
                    $errors["strips.{$strip->id}.{$path}"] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

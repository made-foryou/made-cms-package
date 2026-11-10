<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\DraftChanges;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class CompareDraft
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * The strips the working copy adds, changes, removes or moves compared to the published strips.
     *
     * A strip counts once: a strip that moved and changed is changed. Moved are the strips outside the longest run that
     * kept its order, so one strip dragged to the top is one change. Values are compared in the form the package stores
     * them now (a heading stored as text is its text with the default level, rich text is cleaned), a missing field as
     * its default, without the order of their keys and without the ids of list items, which a restored item gets anew.
     */
    public function handle(Page $page): DraftChanges
    {
        $live = $page->status === PageStatus::Published;

        if ($page->draft_strips === null) {
            return new DraftChanges(live: $live, publishable: ! $live);
        }

        $published = PageStrips::fromArray($page->strips);
        $draft = PageStrips::fromArray($page->draft_strips);

        $added = [];
        $changed = [];
        $kept = [];

        foreach ($draft->strips as $strip) {
            $before = $published->find($strip->id);

            if ($before === null) {
                $added[] = $strip->id;

                continue;
            }

            if (! $this->same($before, $strip)) {
                $changed[] = $strip->id;
            }

            $kept[] = ['id' => $strip->id, 'position' => (int) $published->positionOf($strip->id)];
        }

        $removed = array_values(array_map(
            static fn (Strip $strip): string => $strip->id,
            array_filter($published->strips, static fn (Strip $strip): bool => $draft->find($strip->id) === null),
        ));

        $moved = array_values(array_diff($this->outOfOrder($kept), $changed));
        $count = count($added) + count($changed) + count($removed) + count($moved);

        return new DraftChanges(
            live: $live,
            added: $added,
            changed: $changed,
            removed: $removed,
            moved: $moved,
            publishable: ! $live || $count > 0,
            discardable: $count > 0,
        );
    }

    private function same(Strip $before, Strip $after): bool
    {
        return $before->type === $after->type
            && $before->visible === $after->visible
            && $this->comparable($this->filled($before)) === $this->comparable($this->filled($after));
    }

    /**
     * The values with every field of the type, a missing one from its default, in their stored form; a type that left
     * the catalogue keeps the values it has.
     *
     * @return array<string, mixed>
     */
    private function filled(Strip $strip): array
    {
        if (! $this->types->has($strip->type)) {
            return $strip->values;
        }

        $type = $this->types->find($strip->type);

        return $type->normalize([...$type->defaults(), ...$strip->values]);
    }

    /**
     * The value with the keys of every object in order and without the ids of list items.
     */
    private function comparable(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(function (mixed $item): mixed {
                if (is_array($item) && ! array_is_list($item)) {
                    unset($item['id']);
                }

                return $this->comparable($item);
            }, $value);
        }

        ksort($value);

        return array_map($this->comparable(...), $value);
    }

    /**
     * The strips that left the longest run of strips still in their published order.
     *
     * @param  list<array{id: string, position: int}>  $kept  the strips both versions have, in the order of the draft
     * @return list<string>
     */
    private function outOfOrder(array $kept): array
    {
        $length = [];
        $previous = [];
        $last = null;

        foreach ($kept as $index => $strip) {
            $length[$index] = 1;
            $previous[$index] = null;

            for ($before = 0; $before < $index; $before++) {
                if ($kept[$before]['position'] < $strip['position'] && $length[$index] < $length[$before] + 1) {
                    $length[$index] = $length[$before] + 1;
                    $previous[$index] = $before;
                }
            }

            if ($last === null || $length[$index] > $length[$last]) {
                $last = $index;
            }
        }

        $inOrder = [];

        for ($index = $last; $index !== null; $index = $previous[$index]) {
            $inOrder[] = $kept[$index]['id'];
        }

        return array_values(array_filter(
            array_column($kept, 'id'),
            static fn (string $id): bool => ! in_array($id, $inOrder, true),
        ));
    }
}

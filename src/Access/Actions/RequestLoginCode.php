<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Support\Timebox;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Access\Models\Editor;

/**
 * Sends a login code when the address belongs to an editor, and silently does nothing otherwise.
 * Both take the same time, so neither the answer nor its speed tells whether an account exists.
 */
final class RequestLoginCode
{
    use AsAction;

    private const int MINIMUM_MICROSECONDS = 400_000;

    public function __construct(
        private readonly Timebox $timebox,
    ) {}

    public function handle(LoginCodeRequest $request): void
    {
        $this->timebox->call(function () use ($request): void {
            if (Editor::query()->forEmail($request->email)->exists()) {
                IssueLoginCode::run($request);
            }
        }, self::MINIMUM_MICROSECONDS);
    }
}

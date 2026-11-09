<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Support\Timebox;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\CodeLogin;
use Made\CmsPackage\Access\Data\LoginResult;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\LoginCodeRejected;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Models\LoginCode;

/**
 * Logs in with a code from the mail: the newest active code of the address, asked for in this browser,
 * within the allowed number of attempts. Every failure looks and takes the same.
 */
final class LogInWithCode
{
    use AsAction;

    private const int MINIMUM_MICROSECONDS = 400_000;

    public function __construct(
        private readonly Timebox $timebox,
    ) {}

    public function handle(CodeLogin $login): LoginResult
    {
        return $this->timebox->call(fn (): LoginResult => $this->attempt($login), self::MINIMUM_MICROSECONDS);
    }

    private function attempt(CodeLogin $login): LoginResult
    {
        $maxAttempts = config()->integer('made-cms-package.login_code.max_attempts');

        $loginCode = LoginCode::query()
            ->forEmail(mb_strtolower(trim($login->email)))
            ->active()
            ->boundTo($login->binding)
            ->latestFirst()
            ->first();

        // Counting first and atomically, so parallel guesses can never get past the maximum.
        if ($loginCode === null || LoginCode::query()->whereKey($loginCode->id)->withAttemptsBelow($maxAttempts)->increment('attempts') === 0) {
            throw LoginCodeRejected::for($login->email);
        }

        if (! hash_equals($loginCode->code_hash, IssueLoginCode::make()->codeHash($login->code))) {
            if ($loginCode->attempts + 1 >= $maxAttempts) {
                $loginCode->update(['consumed_at' => now()]);
            }

            throw LoginCodeRejected::for($login->email);
        }

        $editor = Editor::query()->forEmail($loginCode->email)->first();

        // Consuming atomically too, so the same code can never log in twice.
        if ($editor === null || LoginCode::query()->whereKey($loginCode->id)->active()->update(['consumed_at' => now()]) === 0) {
            throw LoginCodeRejected::for($login->email);
        }

        LogInEditor::run($editor, LoginMethod::Code, true);

        return new LoginResult(LoginStatus::LoggedIn, $editor, LoginMethod::Code);
    }
}

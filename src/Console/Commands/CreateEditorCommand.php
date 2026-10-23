<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Access\Actions\CreateEditor;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Exceptions\EditorAlreadyExists;

/**
 * Creates a CMS account until editors can be invited from the CMS itself.
 */
final class CreateEditorCommand extends Command
{
    protected $signature = 'made-cms:editor
        {email : The e-mail address the editor logs in with}
        {--name= : The name of the editor}';

    protected $description = 'Create a Made CMS editor account';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error($this->text('invalid_email', ['email' => $email]));

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? $this->ask($this->text('name')));
        $password = (string) $this->secret($this->text('password'));

        try {
            $editor = CreateEditor::run(new NewEditor(
                email: $email,
                name: $name,
                password: $password === '' ? null : $password,
            ));
        } catch (EditorAlreadyExists) {
            $this->components->error($this->text('exists', ['email' => $email]));

            return self::FAILURE;
        }

        $this->components->info($this->text('created', ['email' => $editor->email]));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        return (string) __('made-cms-package::console.editor.'.$key, $replace);
    }
}

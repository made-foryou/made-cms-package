<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Access\Actions\CreateEditor;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Exceptions\EditorAlreadyExists;

/**
 * Creates a CMS account until editors can be invited from the CMS itself.
 */
final class CreateEditorCommand extends Command
{
    protected $signature = 'made-cms:editor
        {email : The e-mail address the editor logs in with}
        {--name= : The name of the editor}
        {--role=* : A role of the editor (admin, webmaster, content-manager, seo-specialist, owner); asked when left out}';

    protected $description = 'Create a Made CMS editor account';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error($this->text('invalid_email', ['email' => $email]));

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? $this->ask($this->text('name')));

        /** @var list<string> $values */
        $values = $this->option('role') ?: (array) $this->choice($this->text('roles'), $this->roleChoices(), multiple: true);
        $roles = [];

        foreach ($values as $value) {
            $role = Role::tryFrom(trim($value));

            if ($role === null) {
                $this->components->error($this->text('invalid_role', [
                    'role' => $value,
                    'roles' => implode(', ', array_column(Role::cases(), 'value')),
                ]));

                return self::FAILURE;
            }

            $roles[] = $role;
        }

        if ($roles === []) {
            $this->components->error($this->text('no_role'));

            return self::FAILURE;
        }

        $password = (string) $this->secret($this->text('password'));

        try {
            $editor = CreateEditor::run(new NewEditor(
                email: $email,
                name: $name,
                password: $password === '' ? null : $password,
                roles: $roles,
            ));
        } catch (EditorAlreadyExists) {
            $this->components->error($this->text('exists', ['email' => $email]));

            return self::FAILURE;
        }

        $this->components->info($this->text('created', ['email' => $editor->email]));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function roleChoices(): array
    {
        $choices = [];

        foreach (Role::cases() as $role) {
            $choices[$role->value] = $role->label();
        }

        return $choices;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        return (string) __('made-cms-package::console.editor.'.$key, $replace);
    }
}

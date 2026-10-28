<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\ImportPage;
use Made\CmsPackage\Content\Actions\ListPageImports;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Exceptions\InvalidPageImport;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

/**
 * Creates the pages of the page files that the site does not have yet. Safe on every deploy:
 * a page that exists is skipped, whatever editors did to it.
 */
final class ImportPagesCommand extends Command
{
    protected $signature = 'made-cms:pages';

    protected $description = 'Create the Made CMS pages of resources/cms/pages that do not exist yet';

    public function handle(): int
    {
        try {
            /** @var list<PageImport> $imports */
            $imports = ListPageImports::run();
        } catch (InvalidPageImport $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($imports === []) {
            $this->components->info($this->text('none', ['folder' => config()->string('made-cms-package.content.pages_path')]));

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($imports as $import) {
            try {
                $created = ImportPage::run($import);
            } catch (InvalidStripType|InvalidStripContent $exception) {
                $this->components->error($this->text('failed', ['path' => $import->path, 'reason' => $exception->getMessage()]));
                $failed = true;

                continue;
            }

            $this->components->twoColumnDetail($import->path, $created
                ? trans_choice('made-cms-package::console.pages.created', count($import->strips), ['count' => count($import->strips)])
                : $this->text('skipped'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        return (string) __('made-cms-package::console.pages.'.$key, $replace);
    }
}

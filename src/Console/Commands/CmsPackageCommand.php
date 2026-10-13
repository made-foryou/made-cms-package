<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;

class CmsPackageCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'made-cms-package:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package made-cms-package.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('CmsPackage placeholder command executed.');

        return self::SUCCESS;
    }
}

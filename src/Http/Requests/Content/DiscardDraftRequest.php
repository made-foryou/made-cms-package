<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Has no input; it gives the editor who throws the draft away.
 */
final class DiscardDraftRequest extends LauncherRequest {}

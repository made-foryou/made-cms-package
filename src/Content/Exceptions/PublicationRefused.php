<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Strips of a draft that break their definition stop its publication (PutDraftLive), keyed by
 * "strips.<strip id>.<field path>". It is a ValidationException, so the bar of the edit shell gets them as a 422 by
 * strip and field; a plain form and the scheduler catch this one refusal and nothing else.
 */
final class PublicationRefused extends ValidationException {}

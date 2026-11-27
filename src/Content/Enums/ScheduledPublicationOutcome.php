<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * What became of a planned publication when its moment came (PublishScheduledPage).
 */
enum ScheduledPublicationOutcome: string
{
    /** The draft went live. */
    case Published = 'published';

    /** A strip stopped the publication: the draft stays and the page asks for attention. */
    case Refused = 'refused';

    /** The page was live without a draft, so only the plan went. */
    case NothingToPublish = 'nothing_to_publish';

    /** The plan was taken away, moved or published by hand in the meantime, so nothing happened. */
    case Moved = 'moved';
}

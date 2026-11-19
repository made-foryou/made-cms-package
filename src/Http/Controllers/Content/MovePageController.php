<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Made\CmsPackage\Content\Actions\MovePage;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\InvalidPagePosition;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\Content\MovePageRequest;
use Made\CmsPackage\Http\Responses\PageTreeResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moves a page to another place in the tree of the pages screen: another place among its siblings or under another
 * page. The tree reads the screen again afterwards, so there is nothing to answer.
 */
final class MovePageController
{
    public function __invoke(MovePageRequest $request, Page $page): Response
    {
        try {
            MovePage::run($page, $request->parentId(), $request->position());
        } catch (HomePageProtected) {
            $request->reject('parent_id', 'home_protected');
        } catch (CircularPageMove|ParentPageNotFound|ParentPageDeleted) {
            $request->reject('parent_id', 'parent_invalid');
        } catch (InvalidPagePosition) {
            $request->reject('position', 'position_invalid');
        } catch (PagePathTaken) {
            $request->reject('parent_id', 'move_path_taken');
        }

        return PageTreeResponse::moved();
    }
}

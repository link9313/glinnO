<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/UserFrosting
 * @copyright Copyright (c) 2013-2016 Alexander Weissman
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */
namespace UserFrosting\Sprinkle\Site\Controller;

use UserFrosting\Sprinkle\Core\Controller\SimpleController;

/**
 * SiteController Class
 */
class SiteController extends SimpleController
{
    /**
     * Renders nearby map page.
     *
     * Request type: GET
     */
    public function pageNearby($request, $response, $args)
    {
        return $this->ci->view->render($response, 'pages/nearby.html.twig');
    }

    /**
     * Renders calendar page.
     *
     * Request type: GET
     */
    public function pageCalendar($request, $response, $args)
    {
        return $this->ci->view->render($response, 'pages/calendar.html.twig');
    }
}

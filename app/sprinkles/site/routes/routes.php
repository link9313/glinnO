<?php
/**
 * glinnO (http://glinno.herokuapp.com)
 *
 * @link      https://github.com/link9313/glinno
 * @copyright Copyright (c) 2017 Nicholas Lauber
 */

global $app;
$config = $app->getContainer()->get('config');

$app->get('/nearby', 'UserFrosting\Sprinkle\Site\Controller\CoreController:pageNearby');

$app->get('/calendar', 'UserFrosting\Sprinkle\Site\Controller\CoreController:pageCalendar');

$app->get('/calendar', 'UserFrosting\Sprinkle\Site\Controller\CoreController:pageCalendar');

<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/link9313/glinno
 * @copyright Copyright (c) 2017 Nicholas Lauber
 */

/**
 * Routes for event management.
 */
$app->group('/admin/events', function () {
    $this->get('', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:pageList')
        ->setName('uri_events');

    $this->get('/e/{name}', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:pageInfo');
})->add('authGuard');

$app->group('/api/events', function () {
    $this->delete('/e/{name}', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:delete');

    $this->get('', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:getList');

    $this->get('/e/{name}', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:getInfo');

    $this->post('', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:create');

    $this->put('/e/{name}', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:updateInfo');

    $this->put('/e/{name}/{field}', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:updateField');
})->add('authGuard');

$app->group('/modals/events', function () {
    $this->get('/confirm-delete', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:getModalConfirmDelete');

    $this->get('/create', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:getModalCreate');

    $this->get('/edit', 'UserFrosting\Sprinkle\Event\Controller\EventAdminController:getModalEdit');
})->add('authGuard');

$app->group('/event', function () {
    $this->get('', 'UserFrosting\Sprinkle\Event\Controller\EventController:pageCreate');

    $this->post('', 'UserFrosting\Sprinkle\Event\Controller\EventController:create')
        ->setName('create');

    $this->get('/nearby', 'UserFrosting\Sprinkle\Event\Controller\EventController:pageNearby');

    $this->get('/calendar', 'UserFrosting\Sprinkle\Event\Controller\EventController:pageCalendar');
})->add('authGuard');

<?php
/**
 * glinnO (http://glinno.herokuapp.com)
 *
 * @link      https://github.com/link9313/glinnO
 * @copyright Copyright (c) 2017 Nicholas Lauber
 */
namespace UserFrosting\Sprinkle\Event\Controller;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\NotFoundException;
use UserFrosting\Fortress\RequestDataTransformer;
use UserFrosting\Fortress\RequestSchema;
use UserFrosting\Fortress\ServerSideValidator;
use UserFrosting\Fortress\Adapter\JqueryValidationAdapter;
use UserFrosting\Sprinkle\Account\Model\User;
use UserFrosting\Sprinkle\Event\Model\Event;
use UserFrosting\Sprinkle\Core\Controller\SimpleController;
use UserFrosting\Sprinkle\Core\Facades\Debug;
use UserFrosting\Support\Exception\BadRequestException;
use UserFrosting\Support\Exception\ForbiddenException;
use UserFrosting\Support\Exception\HttpException;

/**
 * Controller class for event-related requests, including listing events, CRUD for events, etc.
 *
 * @author Nicholas Lauber (http://glinno.herokuapp.com)
 */
class EventAdminController extends SimpleController
{
    /**
     * Processes the request to create a new event (from the admin controls).
     *
     * Processes the request from the event creation form, checking that:
     * 1. The event name is not already in use;
     * 2. The logged-in user has the necessary permissions to update the posted field(s);
     * 3. The submitted data is valid.
     * This route requires authentication.
     * Request type: POST
     * @see formEventCreate
     */
    public function create($request, $response, $args)
    {
        // Get POST parameters: name, location, start, end, all_day, url, notes, flag_enabled, creator_id
        $params = $request->getParsedBody();

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'create_event')) {
            throw new ForbiddenException();
        }

        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        // Load the request schema
        $schema = new RequestSchema('schema://create.json');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        $error = false;

        // Validate request data
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            $ms->addValidationErrors($validator);
            $error = true;
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        // Check if event name already exists
        if ($classMapper->staticMethod('event', 'exists', $data['name'], 'name')) {
            $ms->addMessageTranslated('danger', 'EVENT.NAME_IN_USE', $data);
            $error = true;
        }

        if ($error) {
            return $response->withStatus(400);
        }

        $data['creator_id'] = $currentUser->id;
        $data['name'] = html_entity_decode($data['name'], ENT_QUOTES);
        $data['location'] = html_entity_decode($data['location'], ENT_QUOTES);
        $data['notes'] = html_entity_decode($data['notes'], ENT_QUOTES);

        /** @var Config $config */
        $config = $this->ci->config;

        // If currentUser does not have permission to add the event, throw an exception.
        if (!$authorizer->checkAccess($currentUser, 'create_event')) {
            throw new ForbiddenException();
        }

        // All checks passed!  log events/activities, create event
        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($classMapper, $data, $ms, $config, $currentUser) {
            // Create the event
            $event = $classMapper->createInstance('event', $data);

            // Store new event to database
            $event->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$currentUser->user_name} created a new event for {$event->name}.", [
                'type' => 'event_create',
                'id' => $event->id
            ]);
        });

        return $response->withStatus(200);
    }

    /**
     * Processes the request to delete an existing event.
     *
     * Deletes the specified event, removing any existing associations.
     * Before doing so, checks that you have permission to delete the target event.
     * Request type: DELETE
     */
    public function delete($request, $response, $args)
    {
        $event = $this->getEventFromParams($args);

        // If the event doesn't exist, return 404
        if (!$event) {
            throw new NotFoundException($request, $response);
        }

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'delete_event', [
            'event' => $event
        ])) {
            throw new ForbiddenException();
        }

        if ($currentUser->id != $event->creator_id) {
            throw new ForbiddenException();
        }

        /** @var Config $config */
        $config = $this->ci->config;

        $eventName = $event->name;

        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($event, $eventName, $currentUser) {
            $event->delete();
            unset($event);

            // Create activity record
            $this->ci->userActivityLogger->info("User {$currentUser->user_name} deleted the event for {$eventName}.", [
                'type' => 'event_delete',
                'event_id' => $event->id
            ]);
        });

        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        $ms->addMessageTranslated('success', 'EVENT.DELETION_SUCCESSFUL', [
            'name' => $eventName
        ]);

        return $response->withStatus(200);
    }

    /**
     * Returns info for a single event.
     *
     * This page requires authentication.
     * Request type: GET
     */
    public function getInfo($request, $response, $args)
    {
        $event = $this->getEventFromParams($args);

        // If the event doesn't exist, return 404
        if (!$event) {
            throw new NotFoundException($request, $response);
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\Event $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'uri_event', [
            'event' => $event
        ])) {
            throw new ForbiddenException();
        }

        $result = $event->toArray();

        // Be careful how you consume this data - it has not been escaped and contains untrusted user-supplied content.
        // For example, if you plan to insert it into an HTML DOM, you must escape it on the client side (or use client-side templating).
        return $response->withJson($result, 200, JSON_PRETTY_PRINT);
    }

    /**
     * Returns a list of Events
     *
     * Generates a list of events, optionally paginated, sorted and/or filtered.
     * This page requires authentication.
     * Request type: GET
     */
    public function getList($request, $response, $args)
    {
        // GET parameters
        $params = $request->getQueryParams();

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'uri_events')) {
            throw new ForbiddenException();
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        $sprunje = $classMapper->createInstance('event_sprunje', $classMapper, $params);

        // Be careful how you consume this data - it has not been escaped and contains untrusted user-supplied content.
        // For example, if you plan to insert it into an HTML DOM, you must escape it on the client side (or use client-side templating).
        return $sprunje->toResponse($response);
    }

    /**
     * Renders the modal form to confirm event deletion.
     *
     * This does NOT render a complete page.  Instead, it renders the HTML for the modal, which can be embedded in other pages.
     * This page requires authentication.
     * Request type: GET
     */
    public function getModalConfirmDelete($request, $response, $args)
    {
        // GET parameters
        $params = $request->getQueryParams();

        $event = $this->getEventFromParams($params);

        // If the event doesn't exist, return 404
        if (!$event) {
            throw new NotFoundException($request, $response);
        }

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'delete_event', [
            'event' => $event
        ])) {
            throw new ForbiddenException();
        }

        /** @var Config $config */
        $config = $this->ci->config;

        return $this->ci->view->render($response, 'components/modals/confirm-delete-event.html.twig', [
            'event' => $event,
            'form' => [
                'action' => "api/events/e/{$event->id}",
            ]
        ]);
    }

    /**
     * Renders the modal form for creating a new event.
     *
     * This does NOT render a complete page.  Instead, it renders the HTML for the modal, which can be embedded in other pages.
     * If the currently logged-in user has permission to create events, then the group toggle will be displayed.
     * This page requires authentication.
     * Request type: GET
     */
    public function getModalCreate($request, $response, $args)
    {
        // GET parameters
        $params = $request->getQueryParams();

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        $translator = $this->ci->translator;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'create_event')) {
            throw new ForbiddenException();
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        /** @var Config $config */
        $config = $this->ci->config;

        // Determine form fields to hide/disable
        // TODO: come back to this when we finish implementing theming
        $fields = [
            'hidden' => [],
            'disabled' => []
        ];

        // Create a dummy event to prepopulate fields
        $data = [];

        $event = $classMapper->createInstance('event', $data);

        // Load validation rules
        $schema = new RequestSchema('schema://create.json');
        $validator = new JqueryValidationAdapter($schema, $this->ci->translator);

        return $this->ci->view->render($response, 'components/modals/event.html.twig', [
            'event' => $event,
            'form' => [
                'action' => 'api/events',
                'method' => 'POST',
                'fields' => $fields,
                'submit_text' => $translator->translate("CREATE")
            ],
            'page' => [
                'validators' => $validator->rules('json', false)
            ]
        ]);
    }

    /**
     * Renders the modal form for editing an existing event.
     *
     * This does NOT render a complete page.  Instead, it renders the HTML for the modal, which can be embedded in other pages.
     * This page requires authentication.
     * Request type: GET
     */
    public function getModalEdit($request, $response, $args)
    {
        // GET parameters
        $params = $request->getQueryParams();

        $event = $this->getEventFromParams($params);

        // If the event doesn't exist, return 404
        if (!$event) {
            throw new NotFoundException($request, $response);
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        // Get the event to edit
        $event = $classMapper->staticMethod('event', 'where', 'id', $event->id)
            ->first();

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled resource - check that currentUser has permission to edit fields "name", "location", "start", "end", "all_day", "url", "notes", "flag_enabled" for this event
        $fieldNames = ['name','location','start','end','all_day','url', 'notes', 'flag_enabled'];

        if (!$authorizer->checkAccess($currentUser, 'update_event_field', [
            'event' => $event,
            'fields' => $fieldNames
        ])) {
            throw new ForbiddenException();
        }

        /** @var Config $config */
        $config = $this->ci->config;

        // Generate form
        $fields = [
            'hidden' => [],
            'disabled' => []
        ];

        // Load validation rules
        $schema = new RequestSchema('schema://edit-info.json');
        $validator = new JqueryValidationAdapter($schema, $this->ci->translator);

        return $this->ci->view->render($response, 'components/modals/event.html.twig', [
            'event' => $event,
            'form' => [
                'action' => "api/events/e/{$event->id}",
                'method' => 'PUT',
                'fields' => $fields,
                'submit_text' => 'Update event'
            ],
            'page' => [
                'validators' => $validator->rules('json', false)
            ]
        ]);
    }

    /**
     * Renders the event listing page.
     *
     * This page renders a table of events, with dropdown menus for admin actions for each event.
     * Actions typically include: edit event details, activate event, enable/disable event, delete event.
     * This page requires authentication.
     * Request type: GET
     */
    public function pageList($request, $response, $args)
    {
        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'uri_events')) {
            throw new ForbiddenException();
        }

        return $this->ci->view->render($response, 'pages/events.html.twig');
    }

    /**
     * Processes the request to update an existing event's basic details (name, location, start, end, all_day, url, notes)
     *
     * Processes the request from the event update form, checking that:
     * 1. The target event's new name, if specified, is not already in use;
     * 2. The logged-in user has the necessary permissions to update the putted field(s);
     * 3. The submitted data is valid.
     * This route requires authentication.
     * Request type: PUT
     */
    public function updateInfo($request, $response, $args)
    {
        // Get the event id from the URL
        $event = $this->getEventFromParams($args);

        if (!$event) {
            throw new NotFoundException($request, $response);
        }

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // Get PUT parameters
        $params = $request->getParsedBody();

        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        // Load the request schema
        $schema = new RequestSchema('schema://edit-info.json');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        $error = false;

        // Validate request data
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            $ms->addValidationErrors($validator);
            $error = true;
        }

        // Determine targeted fields
        $fieldNames = [];
        foreach ($data as $name => $value) {
            if ($name == 'name') {
                $fieldNames[] = 'name';
            } else {
                $fieldNames[] = $name;
            }
        }

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled resource - check that currentUser has permission to edit submitted fields for this event
        if (!$authorizer->checkAccess($currentUser, 'update_event_field', [
            'event' => $event,
            'fields' => array_values(array_unique($fieldNames))
        ])) {
            throw new ForbiddenException();
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        // Check if name already exists
        if (
            isset($data['name']) &&
            $data['name'] != $event->name &&
            $classMapper->staticMethod('event', 'exists', $data['name'], 'name')
        ) {
            $ms->addMessageTranslated('danger', 'EVENT.NAME_IN_USE', $data);
            $error = true;
        }

        if ($error) {
            return $response->withStatus(400);
        }

        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($data, $event, $currentUser) {
            // Update the event and generate success messages
            foreach ($data as $name => $value) {
                if ($value != $event->$name) {
                    $event->$name = $value;
                }
            }

            $event->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$currentUser->user_name} updated event info for event {$event->name}.", [
                'type' => 'event_update_info',
                'event_id' => $event->id
            ]);
        });

        $ms->addMessageTranslated('success', 'EVENT.DETAILS_UPDATED', [
            'name' => $event->name
        ]);
        return $response->withStatus(200);
    }

    /**
     * Processes the request to update a specific field for an existing event.
     *
     * Supports editing all event fields, including password, enabled/disabled status and verification status.
     * Processes the request from the event update form, checking that:
     * 1. The logged-in user has the necessary permissions to update the putted field(s);
     * 2. The submitted data is valid.
     * This route requires authentication.
     * Request type: PUT
     */
    public function updateField($request, $response, $args)
    {
        // Get the event id from the URL
        $event = $this->getEventFromParams($args);

        if (!$event) {
            throw new NotFoundException($request, $response);
        }

        // Get key->value pair from URL and request body
        $fieldName = $args['field'];

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager $authorizer */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled resource - check that currentUser has permission to edit the specified field for this event
        if (!$authorizer->checkAccess($currentUser, 'update_event_field', [
            'event' => $event,
            'fields' => [$fieldName]
        ])) {
            throw new ForbiddenException();
        }

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // Get PUT parameters: value
        $put = $request->getParsedBody();

        if (!isset($put['value'])) {
            throw new BadRequestException();
        }

        // Create and validate key -> value pair
        $params = [
            $fieldName => $put['value']
        ];

        // Load the request schema
        $schema = new RequestSchema('schema://edit-field.json');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        // Validate, and throw exception on validation errors.
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            // TODO: encapsulate the communication of error messages from ServerSideValidator to the BadRequestException
            $e = new BadRequestException();
            foreach ($validator->errors() as $idx => $field) {
                foreach($field as $eidx => $error) {
                    $e->addUserMessage($error);
                }
            }
            throw $e;
        }

        // Get validated and transformed value
        $fieldValue = $data[$fieldName];

        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($fieldName, $fieldValue, $event) {
            $event->$fieldName = $fieldValue;
            $event->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$currentUser->user_name} updated property '$fieldName' for event {$event->name}.", [
                'type' => 'event_update_field',
                'event_id' => $event->id
            ]);
        });

        // Add success messages
        if ($fieldName == 'flag_enabled') {
            if ($fieldValue == '1') {
                $ms->addMessageTranslated('success', 'EVENT.ENABLE_SUCCESSFUL', [
                    'name' => $event->name
                ]);
            } else {
                $ms->addMessageTranslated('success', 'EVENT.DISABLE_SUCCESSFUL', [
                    'name' => $event->name
                ]);
            }
        } else {
            $ms->addMessageTranslated('success', 'EVENT.DETAILS_UPDATED', [
                'name' => $event->name
            ]);
        }

        return $response->withStatus(200);
    }

    /*protected function getEventFromParams($params)
    {
        // Load the request schema
        $schema = new RequestSchema('schema://get-by-name.json');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        // Validate, and throw exception on validation errors.
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            // TODO: encapsulate the communication of error messages from ServerSideValidator to the BadRequestException
            $e = new BadRequestException();
            foreach ($validator->errors() as $idx => $field) {
                foreach($field as $eidx => $error) {
                    $e->addUserMessage($error);
                }
            }
            throw $e;
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper /
        $classMapper = $this->ci->classMapper;

        // Get the event
        $event = $classMapper->staticMethod('event', 'where', 'name', $data['name'])
            ->first();

        return $event;
    } */

    protected function getEventFromParams($params)
    {
        // Load the request schema
        $schema = new RequestSchema('schema://get-by-id.json');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        // Validate, and throw exception on validation errors.
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            // TODO: encapsulate the communication of error messages from ServerSideValidator to the BadRequestException
            $e = new BadRequestException();
            foreach ($validator->errors() as $idx => $field) {
                foreach($field as $eidx => $error) {
                    $e->addUserMessage($error);
                }
            }
            throw $e;
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        // Get the event
        $event = $classMapper->staticMethod('event', 'where', 'id', $data['id'])
            ->first();

        return $event;
    }
}

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
use UserFrosting\Fortress\RequestDataTransformer;
use UserFrosting\Fortress\RequestSchema;
use UserFrosting\Fortress\ServerSideValidator;
use UserFrosting\Fortress\Adapter\JqueryValidationAdapter;
use UserFrosting\Sprinkle\Account\Authenticate\Authenticator;
use UserFrosting\Sprinkle\Account\Controller\Exception\SpammyRequestException;
use UserFrosting\Sprinkle\Account\Model\User;
use UserFrosting\Sprinkle\Event\Model\Event;
use UserFrosting\Sprinkle\Account\Util\Util as AccountUtil;
use UserFrosting\Sprinkle\Core\Controller\SimpleController;
use UserFrosting\Sprinkle\Core\Facades\Debug;
use UserFrosting\Sprinkle\Core\Throttle\Throttler;
use UserFrosting\Sprinkle\Core\Util\Util;
use UserFrosting\Support\Exception\BadRequestException;
use UserFrosting\Support\Exception\ForbiddenException;
use UserFrosting\Support\Exception\HttpException;

/**
 * Controller class for /event/* URLs.  Handles event-related activities, including creation, editing, and deletion.
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 * @see http://www.userfrosting.com/navigating/#structure
 */
class EventController extends SimpleController
{
    /**
     * Check an event name for availability.
     *
     * This route is throttled by default, to discourage abusing it for event enumeration.
     * This route is "public access".
     * Request type: GET
     */
    public function checkName($request, $response, $args)
    {
        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        // GET parameters
        $params = $request->getQueryParams();

        // Load request schema
        $schema = new RequestSchema("schema://get-by-name.json");

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        // Validate, and halt on validation errors.
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            // TODO: encapsulate the communication of error messages from ServerSideValidator to the BadRequestException
            $e = new BadRequestException("Missing or malformed request data!");
            foreach ($validator->errors() as $idx => $field) {
                foreach($field as $eidx => $error) {
                    $e->addEventMessage($error);
                }
            }
            throw $e;
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        $translator = $this->ci->translator;

        if ($classMapper->staticMethod('event', 'exists', $data['name'], 'name')) {
            $message = $translator->translate('EVENT.NAME_NOT_AVAILABLE', $data);
            return $response->write($message)->withStatus(200);
        } else {
            return $response->write('true')->withStatus(200);
        }
    }

    /**
     * Create event page.
     *
     * Provides a form for users to add events.
     * This page requires authentication.
     * Request type: GET
     */
    public function pageCreate($request, $response, $args)
    {
        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'create_event')) {
            $ms->addMessageTranslated("danger", "EVENT.ACCESS_DENIED");
            return $response->withStatus(403);
        }

        // Load validation rules
        $schema = new RequestSchema("schema://create.json");
        $validatorAccountSettings = new JqueryValidationAdapter($schema, $this->ci->translator);

        /** @var Config $config */
        $config = $this->ci->config;

        return $this->ci->view->render($response, 'pages/contrib-event.html.twig', [
            "page" => [
                "validators" => [
                    "create_event"    => $validatorAccountSettings->rules('json', false)
                ],
                "visibility" => ($authorizer->checkAccess($currentUser, "create_event") ? "" : "disabled")
            ]
        ]);
    }

    /**
     * Edit event page.
     *
     * Provides a form for users to add events.
     * This page requires authentication.
     * Request type: GET
     */
    public function pageEdit($request, $response, $args)
    {
        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'uri_edit_event')) {
            throw new ForbiddenException();
        }

        // Load validation rules
        $schema = new RequestSchema("schema://edit-info.json");
        $validatorAccountSettings = new JqueryValidationAdapter($schema, $this->ci->translator);

        /** @var Config $config */
        $config = $this->ci->config;

        return $this->ci->view->render($response, 'pages/contrib-event.html.twig', [
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
     * Processes the request to create a new event.
     *
     * Processes the request from the event creation form, checking that:
     * 1. The event name is not already in use;
     * 2. The logged-in user has the necessary permissions to update the posted field(s);
     * 3. The submitted data is valid.
     * This route requires authentication.
     * Request type: POST
     * @see pageCreate
     */
    public function create($request, $response, $args)
    {
        /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
        $ms = $this->ci->alerts;

        /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager */
        $authorizer = $this->ci->authorizer;

        /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
        $currentUser = $this->ci->currentUser;

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        /** @var UserFrosting\Config\Config $config */
        $config = $this->ci->config;

        // POST parameters
        $params = $request->getParsedBody();

        // Access-controlled page
        if (!$authorizer->checkAccess($currentUser, 'create_event')) {
            $ms->addMessageTranslated("danger", "EVENT.ACCESS_DENIED");
            return $response->withStatus(403);
        }

        // Load the request schema
        $schema = new RequestSchema('schema://create.json');

        // Whitelist and set parameter defaults
        $transformer = new RequestDataTransformer($schema);
        $data = $transformer->transform($params);

        $error = false;

        // Validate, and halt on validation errors.
        $validator = new ServerSideValidator($schema, $this->ci->translator);
        if (!$validator->validate($data)) {
            $ms->addValidationErrors($validator);
            $error = true;
        }

        /** @var UserFrosting\Sprinkle\Core\Throttle\Throttler $throttler */
        $throttler = $this->ci->throttler;
        $delay = $throttler->getDelay('registration_attempt');

        // Throttle requests
        if ($delay > 0) {
            return $response->withStatus(429);
        }

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
        $data['flag_verified'] = false;

        /** @var Config $config */
        $config = $this->ci->config;

        // All checks passed!  log events/activities, create event
        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($classMapper, $data, $ms, $config, $currentUser, $throttler) {
            // Log throttleable event
            $throttler->logEvent('registration_attempt');

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

        $ms->addMessageTranslated("success", "EVENT.CREATED", [
            'name' => $event->name
        ]);
        return $response->withStatus(200);
    }

    /**
     * Returns a list of Events
     *
     * Generates a list of events, optionally paginated, sorted and/or filtered.
     * This page requires authentication.
     * Request type: GET
     */
    public function getCalendar($request, $response, $args)
    {
        // GET parameters
        $params = $request->getQueryParams();

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        $sprunje = $classMapper->createInstance('event_sprunje', $classMapper, $params);

        // Be careful how you consume this data - it has not been escaped and contains untrusted user-supplied content.
        // For example, if you plan to insert it into an HTML DOM, you must escape it on the client side (or use client-side templating).
        return $sprunje->toResponse($response);
    }

    /**
     * Renders nearby map page.
     *
     * Request type: GET
     */
    public function pageMap($request, $response, $args)
    {
        return $this->ci->view->render($response, 'pages/map.html.twig');
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

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

        /** @var UserFrosting\Sprinkle\Core\Throttle\Throttler $throttler */
        $throttler = $this->ci->throttler;
        $delay = $throttler->getDelay('check_name_request');

        // Throttle requests
        if ($delay > 0) {
            return $response->withStatus(429);
        }

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        $translator = $this->ci->translator;

        // Log throttleable event
        $throttler->logEvent('check_username_request');

        if ($classMapper->staticMethod('event', 'exists', $data['name'], 'name')) {
            $message = $translator->translate('NAME.NOT_AVAILABLE', $data);
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
        if (!$authorizer->checkAccess($currentUser, 'uri_create_event')) {
            throw new ForbiddenException();
        }

        // Load validation rules
        $schema = new RequestSchema("schema://create.json");
        $validatorAccountSettings = new JqueryValidationAdapter($schema, $this->ci->translator);

        /** @var Config $config */
        $config = $this->ci->config;


        return $this->ci->view->render($response, 'pages/create-event.html.twig', [
            "page" => [
                "validators" => [
                    "account_settings"    => $validatorAccountSettings->rules('json', false)
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

        return $this->ci->view->render($response, 'pages/edit-event.html.twig', [
            "page" => [
                "validators" => [
                    "update_event"    => $validatorAccountSettings->rules('json', false)
                ],
                "visibility" => ($authorizer->checkAccess($currentUser, "update_event") ? "" : "disabled")
            ]
        ]);
    }

    /**
     * Processes a request to update an event's information.
     *
     * Processes the request from the event form, checking that:
     * 1. They have the necessary permissions to update the posted field(s);
     * 2. The submitted data is valid.
     * This route requires authentication.
     * Request type: POST
     */
     public function update($request, $response, $args)
     {
         /** @var UserFrosting\Sprinkle\Core\MessageStream $ms */
         $ms = $this->ci->alerts;

         /** @var UserFrosting\Sprinkle\Account\Authorize\AuthorizationManager */
         $authorizer = $this->ci->authorizer;

         /** @var UserFrosting\Sprinkle\Account\Model\User $currentUser */
         $currentUser = $this->ci->currentUser;

         /** @var UserFrosting\Sprinkle\Event\Model\Event $currentEvent */
         $event = $classMapper->staticMethod('event', 'where', 'name', $event->name)
             ->first();

         // Access control for entire resource - check that the current user has permission to modify event
         // See recipe "per-field access control" for dynamic fine-grained control over which properties a user can modify.
         if (!$authorizer->checkAccess($currentUser, 'update_event') && ($currentUser->id == $currentEvent->creator_id)) {
             $ms->addMessageTranslated("danger", "EVENT.ACCESS_DENIED");
             return $response->withStatus(403);
         }

         /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
         $classMapper = $this->ci->classMapper;

         /** @var UserFrosting\Config\Config $config */
         $config = $this->ci->config;

         // POST parameters
         $params = $request->getParsedBody();

         // Load the request schema
         $schema = new RequestSchema("schema://edit-info.json");

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

         if ($error) {
             return $response->withStatus(400);
         }

         // Looks good, let's update with new values!
         // Note that only fields listed in `edit-info.json` will be permitted in $data, so this prevents the user from updating all columns in the DB
         $currentEvent->fill($data);

         $currentEvent->save();

         // Create activity record
         $this->ci->userActivityLogger->info("User {$currentUser->user_name} edited event {$currentEvent->name}.", [
             'type' => 'update_event'
         ]);

         $ms->addMessageTranslated("success", "EVENT.UPDATED");
         return $response->withStatus(200);
     }

    /**
     * Processes a new even creation request.
     *
     * This is throttled to prevent account enumeration, since it needs to divulge when a username/email has been used.
     * Processes the request from the form on the registration page, checking that:
     * 1. The honeypot was not modified;
     * 2. The master account has already been created (during installation);
     * 3. Account registration is enabled;
     * 4. The user is not already logged in;
     * 5. Valid information was entered;
     * 6. The captcha, if enabled, is correct;
     * 7. The username and email are not already taken.
     * Automatically sends an activation link upon success, if account activation is enabled.
     * This route is "public access".
     * Request type: POST
     * Returns the User Object for the user record that was created.
     */
    public function create(Request $request, Response $response, $args)
    {
        /** @var MessageStream $ms */
        $ms = $this->ci->alerts;

        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = $this->ci->classMapper;

        /** @var Config $config */
        $config = $this->ci->config;

        // Get POST parameters: name, location, date, time, start_time, end_time, url, notes, all_day
        $params = $request->getParsedBody();

        /** @var UserFrosting\Sprinkle\Account\Authenticate\Authenticator $authenticator */
        $authenticator = $this->ci->authenticator;

        // Load the request schema
        $schema = new RequestSchema("schema://create.json");

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

        /** @var UserFrosting\Sprinkle\Core\Throttle\Throttler $throttler */
        $throttler = $this->ci->throttler;
        $delay = $throttler->getDelay('registration_attempt');

        // Throttle requests
        if ($delay > 0) {
            return $response->withStatus(429);
        }

        // Check if name already exists
        if ($classMapper->staticMethod('event', 'exists', $data['name'], 'name')) {
            $ms->addMessageTranslated("danger", "NAME.IN_USE", $data);
            $error = true;
        }

        if ($error) {
            return $response->withStatus(400);
        }

        $data['flag_verified'] = false;

        // All checks passed!  log events/activities, create user, and send verification email (if required)
        // Begin transaction - DB will be rolled back if an exception occurs
        Capsule::transaction( function() use ($classMapper, $data, $ms, $config, $throttler) {
            // Log throttleable event
            $throttler->logEvent('event_creation_attempt');

            // Create the user
            $user = $classMapper->createInstance('event', $data);

            // Store new user to database
            $event->save();

            // Create activity record
            $this->ci->userActivityLogger->info("User {$user->user_name} created a new event {$event->name}", [
                'type' => 'create_event',
                'user_id' => $user->id
            ]);

            $ms->addMessageTranslated("success", "EVENT.COMPLETE_TYPE1");
        });

        return $response->withStatus(200);
    }
}

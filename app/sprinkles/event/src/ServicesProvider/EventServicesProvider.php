<?php
/**
 * glinnO (https://glinno.herokuapp.com)
 *
 * @link      https://github.com/link9313/glinnO
 * @copyright Copyright (c) 2017 Nicholas Lauber
 */
namespace UserFrosting\Sprinkle\Event\ServicesProvider;

use UserFrosting\Sprinkle\Core\Util\ClassMapper;
use UserFrosting\Sprinkle\Core\Facades\Debug;

/**
 * Registers services for the admin sprinkle.
 *
 * @author Nicholas Lauber
 */
class EventServicesProvider
{
    /**
     * Register UserFrosting's admin services.
     *
     * @param Container $container A DI container implementing ArrayAccess and container-interop.
     */
    public function register($container)
    {
        /**
         * Extend the 'classMapper' service to register sprunje classes.
         *
         * Mappings added: Event, 'event_sprunje'
         */
        $container->extend('classMapper', function ($classMapper, $c) {
            $classMapper->setClassMapping('event', 'UserFrosting\Sprinkle\Event\Model\Event');
            $classMapper->setClassMapping('event_sprunje', 'UserFrosting\Sprinkle\Event\Sprunje\EventSprunje');
            return $classMapper;
        });
    }
}

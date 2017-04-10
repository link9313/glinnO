<?php
/**
 * glinnO (https://glinno.herokuapp.com)
 *
 * @link      https://github.com/link9313/glinno
 * @copyright Copyright (c) 2017 Nicholas Lauber
 */
namespace UserFrosting\Sprinkle\Event;

use UserFrosting\Sprinkle\Event\ServicesProvider\EventServicesProvider;
use UserFrosting\Sprinkle\Core\Initialize\Sprinkle;

/**
 * Bootstrapper class for the 'event' sprinkle.
 *
 * @author Nicholas Lauber (https://glinno.herokuapp.com)
 */
class Event extends Sprinkle
{
    /**
     * Register Event services.
     */
    public function init()
    {
        $serviceProvider = new EventServicesProvider();
        $serviceProvider->register($this->ci);
    }
}

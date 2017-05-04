<?php
/**
 * glinnO (https://glinno.herokuapp.com)
 *
 * @link      https://github.com/link9313/glinno
 * @copyright Copyright (c) 2017 Nicholas Lauber
 */
namespace UserFrosting\Sprinkle\Event\Sprunje;

use Illuminate\Database\Capsule\Manager as Capsule;
use UserFrosting\Sprinkle\Core\Facades\Debug;
use UserFrosting\Sprinkle\Core\Sprunje\Sprunje;

/**
 * EventSprunje
 *
 * Implements Sprunje for the events API.
 *
 * @author Nicholas Lauber (https://glinno.herokuapp.com)
 */
class EventSprunje extends Sprunje
{
    protected $name = 'events';

    protected $sortable = [
        'id',
        'name',
        'notes'
    ];

    protected $filterable = [
        'id',
        'name',
        'notes'
    ];

    /**
     * {@inheritDoc}
     */
    protected function baseQuery()
    {
        $query = $this->classMapper->createInstance('event');

        return $query;
    }

    /**
     * Filter LIKE name OR notes.
     *
     * @param Builder $query
     * @param mixed $value
     * @return Builder
     */
    protected function filterInfo($query, $value)
    {
        // Split value on separator for OR queries
        $values = explode($this->orSeparator, $value);
        return $query->where(function ($query) use ($values) {
            foreach ($values as $value) {
                $query = $query->orLike('name', $value)
                                ->orLike('notes', $value);
            }
            return $query;
        });
    }
}

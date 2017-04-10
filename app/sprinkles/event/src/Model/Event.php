<?php
/**
 * glinnO (http://glinno.herokuapp.com)
 *
 * @link      https://github.com/link9313/glinno
 * @copyright Copyright (c) 2017 Nicholas Lauber
 */
namespace UserFrosting\Sprinkle\Event\Model;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\SoftDeletes;
use UserFrosting\Sprinkle\Core\Facades\Debug;
use UserFrosting\Sprinkle\Core\Model\UFModel;

/**
 * Event Class
 *
 * Represents an Event object as stored in the database.
 *
 * @author Nicholas Lauber
 * @property int id
 * @property string name
 * @property string location
 * @property date date
 * @property bool all_day
 * @property time start_time
 * @property time end_time
 * @property string url
 * @property string notes
 * @property bool flag_enabled
 * @property int creator_id
 * @property timestamp deletedAt
 */
class Event extends UFModel
{
    use SoftDeletes;

    /**
     * @var string The name of the table for the current model.
     */
    protected $table = "events";

    protected $fillable = [
        "id",
        "name",
        "location",
        "date",
        "all_day",
        "start_time",
        "end_time",
        "url",
        "notes",
        "flag_enabled",
        "creator_id",
        "deletedAt"
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['date', 'deletedAt'];

    /**
     * @var bool Enable timestamps for Events.
     */
    public $timestamps = true;

    /**
     * Determine if the property for this object exists.
     * We add relations here so that Twig will be able to find them.
     * See http://stackoverflow.com/questions/29514081/cannot-access-eloquent-attributes-on-twig/35908957#35908957
     * Every property in __get must also be implemented here for Twig to recognize it.
     * @param string $name the name of the property to check.
     * @return bool true if the property is defined, false otherwise.
     */
    public function __isset($name)
    {
        return parent::__isset($name);
    }

    /**
     * Get a property for this object.
     *
     * @param string $name the name of the property to retrieve.
     * @throws Exception the property does not exist for this object.
     * @return string the associated property.
     */
    public function __get($name)
    {
        return parent::__get($name);
    }

    /**
     * Get all activities for this user.
     */
    public function activities()
    {
        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = static::$ci->classMapper;

        return $this->hasMany($classMapper->getClassMapping('activity'), 'creator_id');
    }

    /**
     * Delete this event from the database, along with any linked activities.
     *
     * @param bool $hardDelete Set to true to completely remove the user and all associated objects.
     * @return bool true if the deletion was successful, false otherwise.
     */
    public function delete($hardDelete = false)
    {
        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = static::$ci->classMapper;

        if ($hardDelete) {
            // Remove all user activities
            $classMapper->staticMethod('activity', 'where', 'user_id', $this->creator_id, '&&', 'id', $this->id)->delete();

            // TODO: remove any persistences

            // Delete the event
            $result = parent::forceDelete();
        } else {
            // Soft delete the event, leaving all associated records alone
            $result = parent::delete();
        }

        return $result;
    }

    /**
     * Determines whether an event exists, including checking soft-deleted records
     *
     * @param mixed $value
     * @param string $identifier
     * @param bool $checkDeleted set to true to include soft-deleted records
     * @return User|null
     */
    public static function exists($value, $identifier = 'name', $checkDeleted = true)
    {
        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = static::$ci->classMapper;

        $query = $classMapper->staticMethod('event', 'where', $identifier, $value);

        if ($checkDeleted) {
            $query = $query->withTrashed();
        }

        return $query->first();
    }
}

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
 * @author Nicholas Lauber
 * @property int id
 * @property string name
 * @property string location
 * @property timestamp start_time
 * @property timestamp end_time
 * @property bool all_day
 * @property string url
 * @property string notes
 * @property bool flag_enabled
 * @property int creator_id
 * @property timestamp created_at
 * @property timestamp updated_at
 * @property timestamp deleted_at
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
        "start",
        "end",
        "all_day",
        "url",
        "notes",
        "flag_enabled",
        "creator_id",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var bool Enable timestamps for Events.
     */
    public $timestamps = true;

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
            $classMapper->staticMethod('activity', 'where', 'creator_id', $this->creator_id, '&&', 'id', $this->id)->delete();

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

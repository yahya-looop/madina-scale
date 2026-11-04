<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $user_id
 * @property int $location_id
 * @property string $access_level
 * @property int|null $assigned_by
 */
class LocationUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'location_user';

    protected $guarded = [];
}

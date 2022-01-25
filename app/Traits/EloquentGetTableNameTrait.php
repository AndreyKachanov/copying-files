<?php

namespace App\Traits;

trait EloquentGetTableNameTrait
{
    public static function getTableName()
    {
        return ((new self)->getTable());
    }
}

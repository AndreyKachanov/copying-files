<?php

namespace App\Models;

use App\Traits\EloquentGetTableNameTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Yajra\Oci8\Eloquent\OracleEloquent as Eloquent;

class File extends Model
//class File extends Eloquent
{
    use EloquentGetTableNameTrait;
    use HasFactory;

    public $timestamps = true;
//    protected $table = 'copying_files_test';
//    protected $primaryKey = 'id';
    protected $table = 'copying_files';
//    public $sequence = 'COPYING_FILES_ID_SEQ';
//    public $sequence = 'copying_files_id_seq';
//    public $incrementing = true;
    protected $fillable = [
        'id',
        'name',
        'sqn',
        'md5sum',
        'file_size',
        'is_skipped',
        'is_copied',
        'is_bad_copied',
        'copy_info',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'copy_info' => 'array'
    ];
//    protected $casts = [
//        'created_at' => 'datetime:Y-m-d',
//        'updated_at' => 'datetime:Y-m-d'
//    ];
    protected $dateFormat = 'd.m.Y H:i:s.u';
//    protected $dateFormat = 'Y-m-d H:i:s';
//    protected $fillable = ['*'];
//    public $sequence = 'COPYING_FILES_ID_SEQ';

//    protected static function booted()
//    {
//        static::created(function ($file) {
//            $filesArr = File::pluck('sqn')->all();
//            $filesArr = array_map('intval', File::pluck('sqn')->all());
//            if (!checkIsIncreasingSequence(array_map('intval', $filesArr))) {
//                sendTelegram('checkIsIncreasingSequence = false');
//            }
//        });
//    }
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('d.m.Y H:i:s.u');
    }

}

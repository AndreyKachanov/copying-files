<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\File;

class CreateCopyingFilesTable extends Migration
{
    private $tableName;

    public function __construct()
    {
        $this->tableName = File::getTableName();
//        dd($this->tableName);
    }
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable($this->tableName)) {
            Schema::create($this->tableName, function (Blueprint $table) {
                $table->increments('id')->comment('Autoincrement');
                $table->string('name')->unique()->comment('Имя файла архивного журнала');
                $table->unsignedSmallInteger('sqn')->unique()->comment('Sequence архивного журнала');
                $table->integer('file_size', 11)->nullable()->comment('Размер файла в байтах');
                $table->string('md5sum')->nullable()->comment('md5sum файла на момент копирования');
                $table->boolean('is_skipped')->default(false)->comment('Файл был занесен в данную таблицу после простоя скрипта');
                $table->boolean('is_copied')->default(false)->comment('Попытка копирования файла');
                $table->boolean('is_push_json_log')->default(false)->comment('Отправлен ли json лог на другой сервер');
                $table->boolean('is_bad_copied')->default(false)->comment('Копирование файла в какую либо папку с ошибкой (rsync вернул код не равный 0');
                $table->string('copy_info', 4000)->nullable()->comment('Json объект. Информация о копировании файла по папкам');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists($this->tableName);
    }
}

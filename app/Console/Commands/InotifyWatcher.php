<?php

namespace App\Console\Commands;

use App\Models\File;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InotifyWatcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inotify:add:watch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        exec("ps aux | grep $this->signature | grep -v grep | awk '{ print $13 }'", $output, $exitCode);
        if (is_array($output) && count(array_keys($output, $this->signature)) > 1) {
            $this->logError('Команда ' . $this->signature . ' не будет запущена, т.к. 1 экземпляр уже запущен!');
            return Command::FAILURE;
        }

//        DB::statement("ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS.FF6'");
//       dump(DB::select("SELECT PARAMETER, VALUE FROM nls_session_parameters where parameter='NLS_TIMESTAMP_FORMAT'"));
        $mainDest = '/oracle/backup/arch';

//        if (File::count() > 0) {
//            $random = mt_rand(1, 999999);
//            $skipped_arr = DB::table('v$archived_log')->select(
//                DB::raw("regexp_substr(name, '[^\/]*$') as name"),
//                'sequence# as sqn',
//                DB::raw('1 as is_skipped')
//            )->where('sequence#', '>', File::max('sqn'))->get();
//
//            if ($skipped_arr->count() > 0) {
//                $this->log('-------------------------------Start------------------------------', $random);
//                $skipped_arr = $skipped_arr->map(function (object $item, int $key):array {
//                    $item = (array) $item;
//                    $time = Carbon::now()->format('d.m.Y H:i:s.u');
//                    $item['created_at'] = $time;
//                    $item['updated_at'] = $time;
//                    return $item;
//                })->toArray();
//
//                $this->setNlsTimestampFormat('DD.MM.YYYY HH24:MI:SS.FF6');
//                File::insert($skipped_arr);
//                $this->log('Вставлено ' . count($skipped_arr) . ' пропущенных журналов. Sequence ' . $skipped_arr[0]['sqn'] . '..' . $skipped_arr[count($skipped_arr) - 1]['sqn'], $random);
//                $this->runCmd('copying:files', $random);
//                $this->log('-------------------------------END--------------------------------', $random);
//            }
//        }

//        $lastFileNameFromView = DB::table('v$archived_log')->select('name')->latest('recid')->first()->name;

        $filesArr = [];
        $fd = inotify_init();

        $watch_descriptor = inotify_add_watch($fd, $mainDest, IN_CLOSE_WRITE);
        while (true) {
            $random = mt_rand(1, 999999);
//            dump(DB::select("SELECT PARAMETER, VALUE FROM nls_session_parameters where parameter='NLS_TIMESTAMP_FORMAT'"));
            $events = inotify_read($fd);
            $fileName = $events[0]['name'];
            $this->log('-------------------------------Start------------------------------', $random);
            $this->log('Появился файл - ' . $fileName, $random);

            if ((preg_match('/.*1_([0-9]{1,6})_.+/', $fileName)) === 0) {
                $this->log('Файл - ' . $fileName . ' не соответствует шаблону архивного журнала. Continue to next iteration.', $random);
                $this->log('-------------------------------END--------------------------------', $random);
                continue;
            }

            $numberFromInotify = getNumberFromFileName($fileName);
            $this->log((isset($lastFileNameFromLoop)) ? '$lastFileNameFromLoop=' . $lastFileNameFromLoop : 'lastFileNameFromLoop not isset', $random);
            $lastFileName = ($lastFileNameFromLoop ?? DB::table('v$archived_log')->select(DB::raw("regexp_substr(name, '[^\/]*$') as name"))->latest('recid')->first()->name) ;
            $isLastNumber = getNumberFromFileName($lastFileName);

            $this->log('filesArr - ' . print_r($filesArr, true), $random);
            $this->log('Функция checkIfLastArchive возвращает - ' . ((checkIfLastArchive($numberFromInotify, $isLastNumber)) ? 'true' : 'false'), $random);

            if (checkIfLastArchive($numberFromInotify, $isLastNumber)) {
                $this->log('Файл ' . $fileName . ' является последним.', $random);
                if (!in_array($fileName, $filesArr)) {
                    $this->log('Файла ' . $fileName . ' нет в массиве, т.е. в бд еще не записан', $random);
                    $this->setNlsTimestampFormat('DD.MM.YYYY HH24:MI:SS.FF6');
                    if (File::create([
                        'name' => $fileName,
                        'sqn' => $numberFromInotify
                    ])) {

//                        Если сбилась последовательность файлов
                        if (!checkIsIncreasingSequence(array_map('intval', File::pluck('sqn')->all()))) {
                            $msg = 'Сбилась последовательность файлов. checkIsIncreasingSequence = false';
                            $this->logError($msg, $random);
                            sendTelegram($msg);
                            die;
                        }
                        $this->log('Последовательность файлов не нарушена', $random);
                        $this->log('Файл ' . $fileName . ' записан в бд.', $random);
//                        $this->runCopyingCommand($random);
                        $this->runCmd('copying:files', $random);
                        $filesArr[] = $fileName;
                        $this->log('Файл ' . $fileName . ' записан в массив', $random);
                        $lastFileNameFromLoop = $fileName;
                    }
                } else {
                    $this->logError('Файл ' . $fileName . ' уже есть в массиве, т.е. он уже записан в бд., пропускаем его', $random);
                }
            } else {
                $this->logError('Файл ' . $fileName . ' не является последним архивом', $random);
            }
            $this->log('-------------------------------END--------------------------------', $random);
        }
    }

    /**
     * @param string $format
     */
    private function setNlsTimestampFormat(string $format) {
        DB::statement("ALTER SESSION SET NLS_TIMESTAMP_FORMAT='$format'");
    }

    private function log(string $str, $random = '') {
        $time = Carbon::now()->format('d.m.Y H:i:s.u');
        $log = '[' . ( ($random == '') ? $time : $random . '; ' . $time) . '] ' . $str;
        $this->info($log);
    }

    private function logError(string $str, $random = '') {
        $time = Carbon::now()->format('d.m.Y H:i:s.u');

        $log = '[' . ( ($random == '') ? $time : $random . '; ' . $time) . '] ' . $str;
        $this->error($log);
    }

    private function runCmd(string $command, int $random) {
        $runCommand = 'nohup /usr/bin/php /home/oracle/new_script2/copying-files/artisan ' . $command . ' > /dev/null 2>&1 &';
        if ($this->checkIsRunningCommand($command, $random)) {
            $this->log('Команда ' . $runCommand . ' не запущена, запускаем', $random);
            exec($runCommand, $output, $exitCode);
            if ($exitCode != 0) {
                $msg = 'Команда ' . $runCommand . ' выполнилась с ошибкой, exit_code=' . $exitCode;
                $this->logError($msg, $random);
                sendTelegram($msg);
            } else {
                $this->log('Успешно выполнена команда - ' . $runCommand . ', exit_code=' . $exitCode, $random);
            }
        } else {
            $this->logError('Команда ' . $runCommand . ' запущена не будет, она уже выполняется в текущий момент', $random);
        }
    }
    private function checkIsRunningCommand(string $command, $random = '') {
        $checkCommand = "ps aux | grep '$command' | grep -v grep | awk '{ print $13 }'";
        exec($checkCommand, $output, $exitCode);
        if ($exitCode != 0) {
            $msg = 'Проверяющая команда ' . $checkCommand . ' выполнилась с ошибкой, exit_code=' . $exitCode;
            $this->logError($msg . $exitCode, $random);
            sendTelegram($msg);
        } else {
            $this->log('Проверяющая команда ' . $checkCommand .' выполнилась успешно , exit_code=' . $exitCode, $random);
            return (array_search($command, $output) === false);
        }
    }
}

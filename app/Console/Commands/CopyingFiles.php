<?php

namespace App\Console\Commands;

use App\Models\File;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CopyingFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copying:files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    private $random;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->random = mt_rand(1, 999999);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->log('----------------------Start--------------------------------------------------');

        $scriptName = basename(__FILE__);
        $lockFile = '/tmp/' . $scriptName . '.lock';

        if (file_exists($lockFile)) {
            $this->log('file ' . $lockFile . ' exists, die!');
            die;
        } else {
            $this->log('file ' . $lockFile . ' not exists, GO and lock script');
            file_put_contents($lockFile, 'Do not remove ' . $lockFile);
        }

        $mainDest = '/oracle/backup/arch';
        $dirs = [
            '/home/oracle/new_script/dir1',
            '/home/oracle/new_script/dir2',
            '/home/oracle/new_script/dir111',
//            'andrey@192.168.0.106:/home/andrey/test111'
//            '/home/oracle/new_script/dir3'
        ];

        $i = 1;
        while (File::where('is_copied', false)->count() > 0) {
            $this->log( '--------------------------------------------');
            $this->log('(count is_copied > 0) = true, зашли в цикл. Итерация № ' . $i);
            $rows = File::select('id', 'name', 'sqn')->where('is_copied', false)->orderBy('id')->get();
            foreach ($rows as $row) {
                $sqn = $row['sqn'];
                if (DB::table('v$archived_log')->where('SEQUENCE#', $sqn)->count() > 0) {
                    $this->log('archive ' . $row['name'] . ' in view v$archived_log');
                    $this->log('start copying files');
                    $md5sum = md5_file($mainDest . '/' . $row['name']);
                    $this->log('md5sum ' . $row['name'] . '=' . $md5sum);
                    $fileSize = filesize($mainDest . '/' . $row['name']);
                    $this->log('File size in MB=' . $fileSize);

                    if (count($dirs) > 0) {
                        $infoArr = [];
                        foreach ($dirs as $dir) {

                            if (!is_dir($dir)) {
                                $infoArr[$dir] = ['is_dir' => false];
                                continue;
                            }

                            $dirArr = [];
                            $dirArr['is_dir'] = true;
                            $runCommand = 'rsync -z -c ' . $mainDest . '/' . $row['name'] .  ' ' . $dir;
                            $this->log('Run command - ' . $runCommand);
                            $startTime = Carbon::now();
                            exec($runCommand, $output, $exitCode);
                            $finishTime = Carbon::now();
                            $dirArr['exit_code'] = $exitCode;
                            $dirArr['start_copied'] = $startTime->format('d.m.Y H:i:s.u');
                            // если команда выполнилась с ошибкой
                            if ($exitCode != 0) {
                                $msg = '! Ошибка запуска команды ' . $runCommand . '. Функция exec вернула exit code=' . $exitCode;
                                $this->log($msg);
                                sendTelegram($msg);
                            } else {
                                $dirArr['finish_copied'] = $finishTime->format('d.m.Y H:i:s.u');
                                $this->log('Copied file ' . $row['name'] . ' to directory ' . $dir . ', result (exit code)=' . $exitCode);
//                                $dirArr['copy_time_seconds'] = $finishTime->diffInSeconds($startTime);
                            }

                            $infoArr[$dir] = $dirArr;
                        }

//                    $infoArr['/home/oracle/new_script/dir3']['exit_code'] = 127;
//                        $infoArr['/home/oracle/new_script/dir2']['exit_code'] = 127;
                        $this->log(print_r($infoArr, true));

                        $this->setNlsTimestampFormat('DD.MM.YYYY HH24:MI:SS.FF6');
//                        if (File::where('id', $row['id'])
                        $file = File::find($row['id']);
                        $file->is_copied = true;
                        $file->is_bad_copied = (string) collect($infoArr)->filter( fn($item) => ($item['is_dir'] && $item['exit_code'] !== 0))->count();
                        $file->copy_info = collect($infoArr)->toJson();
                        $file->copy_info = $infoArr;
//                        $file->copy_info = json_encode($infoArr, JSON_UNESCAPED_SLASHES);
                        $file->md5sum = $md5sum;
                        $file->file_size = $fileSize;
                        if ($this->sendJsonLog($file)) {
                            $this->log('Json log отправлен на сервер');
                            $file->is_push_json_log = true;
                        }

                        if (!$file->save()) {
                            $msg = '!!! file->save. Ошибка обновления данных в бд. id=' . $row['id'] . '. Die';
                            $this->log($msg);
                            sendTelegram($msg);
                            die;
                        }

                        $this->log('Запись в бд с id=' . $row['id'] . ' успешно обновлена');

                    } else {
                        $this->log('! Массив dirs не имеет директорий');
                    }
                } else {
                    $this->log('archive ' . $row['name'] . ' not in view v$archived_log');
                }
            }
            $i++;
        }
        $this->log( '--------------------------------------------');
        $this->log('Вышли из цикла. (count is_copied > 0) = false');

        if (file_exists($lockFile)) {
            unlink($lockFile);
            $this->log('Unlock script. Removed file ' . $lockFile );
        }

        $this->log('----------------------End--------------------------------------------------');
        return Command::SUCCESS;
    }

    private function log(string $str) {
        $time = Carbon::now()->format('d.m.Y H:i:s.u');
        $log = '[' . $this->random  . '; ' . $time . '] ' . $str;
        $this->info($log);
        Storage::append('file.txt', $log);
    }

    /**
     * @param string $format
     */
    private function setNlsTimestampFormat(string $format) {
        DB::statement("ALTER SESSION SET NLS_TIMESTAMP_FORMAT='$format'");
    }

    private function sendJsonLog(File $file): bool {
//        $file->copy_info = json_decode($file->copy_info);
        $json = addslashes($file->toJson());
        $serv = 'andrey@192.168.0.110';
        $command = 'cp /home/andrey/json.json /home/andrey/json.json.tmp && jq ".[.| length] |= . +  ' . $json . '" /home/andrey/json.json.tmp > /home/andrey/json.json && rm /home/andrey/json.json.tmp';
        $run = "ssh {$serv} '{$command}'";
        $this->log('Команда отправки лога - ');
        $this->log($run);
        exec($run, $output, $exitCode);
//
        if ($exitCode != 0) {
            $msg = '!Ошибка отправки json лога на сервер (sendJsonLog). Функция exec вернула exit code=' . $exitCode;
            $this->log($msg);
            sendTelegram($msg);
            die;
        }

        return true;
    }
}

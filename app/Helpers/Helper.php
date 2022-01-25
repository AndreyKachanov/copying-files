<?php

use GuzzleHttp\Client as GuzzleClient;

function getNumberFromFileName (string $filename): int {
//        dump($filename);
        $explode_arr = explode('/', $filename);
//        dump($explode_arr);
        $matches = [];
        preg_match('/.*1_([0-9]{1,6})_.+/', $filename, $matches);
//        dd(preg_match('/.*1_([0-9]{1,6})_.+/', $filename));
//        dd(isset($matches[1]));
        return (int) $matches[1];
    }

    function checkIfLastArchive(int $number1, int $number2): bool {
        return ($number1 == $number2) || ($number1 == $number2 + 1);
    }

    function sendTelegram(string $message) {
        echo 'Send telegram - ' . $message;
//        $client = new GuzzleClient();


//        wget "https://api.telegram.org/bot2047219189:AAGTyPmyxMQOm1G9QwEI2PPIB2N4PjYy-LY/sendMessage?parse_mode=HTML&text=$1&chat_id=-1001512482168"
//        chat id: -1001582976887, bot - 2011419669:AAFujsmD4lCY0GBGAFN9kkynqiszjHY4i30


//        $request = "https://api.telegram.org/bot2011419669:AAFujsmD4lCY0GBGAFN9kkynqiszjHY4i30/sendMessage?parse_mode=HTML&text=$message&chat_id=-1001582976887";
//
//        try {
//            $response = $client->get($request)->getBody()->getContents();
//            $json = json_decode($response, true);
//            if ($json['status'] != 'success') {
//                dd("return status != success  -" . $request);
//            }
//        } catch (Exception $e) {
//            dd("Error - " . $e->getMessage() . ', line - ' . $e->getLine() . ' File - ' . $e->getFile());
//        }

//        return json_decode($response, true);
    }

//    true - последовательность не сбилась
//    false - последовательность сбилась
    function checkIsIncreasingSequence(array $numbers) {
        return !array_filter(array_map(
            function($b,$a){ return $b-$a-1; },   //  --> [0,0,0]
            array_slice($numbers,1),              //  ^   [5,6,7]
            array_slice($numbers,0,-1))           //   ^  [4,5,6]
        );
    }

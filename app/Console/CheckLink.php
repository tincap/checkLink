<?php

namespace app\Console;

use app\Console\Helpers\ConsoleHelpers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use tincap\Bot\Exceptions\ConfigException;
use tincap\XpartnersBot\Exceptions\LoginException;
use tincap\XpartnersBot\Exceptions\TokenException;
use tincap\XpartnersBot\XpartnersBot;

include __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../src/Autoloader.php';

date_default_timezone_set('Europe/Moscow');

class CheckLink
{
    public $config;

    public function __construct()
    {
        $this->config = [
            'xpartners' => require __DIR__ . '/../config/xpartners.php',
            'params' => require __DIR__ . '/../config/params.php',
        ];
    }

    /**
     * Проверяем на работоспособность ссылку
     *
     * @return bool
     */
    public function checkLink()
    {
        $client = new Client();

        try {
            $client->request('GET', $this->config['params']['link'], [
                'allow_redirects' => true,
                'timeout' => 3,
                'connect_timeout' => 3,
            ]);

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * Постбеком обновляем ссылку
     *
     * @param $newLink
     */
    public function postback($newLink)
    {
        try {
            $client = new Client();

            $this->log("Отправили постбек", __DIR__ . '/../../logs/postback.txt');

            $content = $client->request('POST', $this->config['params']['updateLinkHref'], [
                RequestOptions::FORM_PARAMS => [
                    'new_link' => $newLink,
                ],
            ])->getBody()->getContents();

            if ($content == "OK") {
                ConsoleHelpers::log("Успешно обновили ссыылку", 32);
            } else {
                ConsoleHelpers::log("Ошибка постбека: " . $content, 31);
            }
        } catch (GuzzleException $e) {
            $this->log("Postback Error", __DIR__ . '/../../logs/errors.txt');
            ConsoleHelpers::log("Postback Error", 31);
        }
    }

    public function getNewLink()
    {
        try {
            $xpartners = new XpartnersBot($this->config['xpartners']);
            $xpartners->login();
            $json = $xpartners->generateNewLink($this->config['params']['subId']);

            $data = \GuzzleHttp\json_decode($json);

            if (isset($data->Data)) {
                $linkData = \GuzzleHttp\json_decode($data->Data);
                if (isset($linkData->link)) {
                    ConsoleHelpers::log("Успешно сгенерировали ссылку: " . $linkData->link, 32);
                    return $linkData->link;
                }
            }

        } catch (GuzzleException $e) {
            ConsoleHelpers::log($e->getMessage(), 31);
            ConsoleHelpers::log("Error", 31);
        } catch (LoginException $e) {
            ConsoleHelpers::log("Login Error", 31);
        } catch (ConfigException $e) {
            ConsoleHelpers::log("Config Error", 31);
        } catch (TokenException $e) {
            ConsoleHelpers::log("Token Error", 31);
        }

        return false;
    }

    /**
     * Функция запоминает посетителя
     * @param $message
     * @param $filePath
     */
    function log($message, $filePath)
    {
        if (!file_exists($filePath)) {
            $fp = fopen($filePath, 'w');
        } else {
            $fp = fopen($filePath, 'a+');
        }

        $message = date('d.m.Y H:i') . ' ' . $message . "\n";

        fwrite($fp, $message);

        fclose($fp);
    }
}

$checkLink = new CheckLink();

if (!$checkLink->checkLink()) {
    $checkLink->postback($checkLink->getNewLink());
} else {
    $checkLink->log("Ссылка работает", __DIR__ . '/../../logs/success.txt');
    ConsoleHelpers::log("Ссылка работает", 32);
}
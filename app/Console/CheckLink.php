<?php

namespace app\Console;

use app\Console\Helpers\ConsoleHelpers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
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
    public $xpartnersConfig;

    public function __construct()
    {
        $this->xpartnersConfig = require __DIR__ . '/../config/xpartners.php';
    }

    /**
     * Проверяем на работоспособность ссылку
     *
     * @param $link
     * @return bool
     */
    public function checkLink($link)
    {
        try {
            $container = [];
            $history = Middleware::history($container);
            $stack = HandlerStack::create();
            $stack->push($history);

            $client = new Client(['handler' => $stack]);

            echo $link . "\n";

            $response = $client->request('GET', $link, [
                'allow_redirects' => true,
                'timeout' => 4,
                'connect_timeout' => 4,
//                'proxy' => "http://{$this->xpartnersConfig['proxy_auth']}@{$this->xpartnersConfig['proxy_ip']}",
                RequestOptions::HEADERS => [
                    'Referer' => 'https://instagram.com',
                    'User-Agent' => 'instagram instagram instagram',
                ],
            ]);

            if (preg_match('/Доступ ограничен/u', $response->getBody()->getContents())) {
                return false;
            }

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * Постбеком обновляем ссылку
     *
     * @param $newLink - новая ссылка
     * @param $updateLinkHref  - ссылка куда отправляется POST
     */
    public function postback($newLink, $updateLinkHref)
    {
        try {
            $client = new Client();

            $this->log("Отправили постбек $updateLinkHref", __DIR__ . '/../../logs/postback.txt');

            $content = $client->request('POST', $updateLinkHref, [
                RequestOptions::FORM_PARAMS => [
                    'new_link' => $newLink,
                ],
            ])->getBody()->getContents();

            if ($content == "OK") {
                ConsoleHelpers::log("Успешно обновили ссыылку", 32);
            } else {
                ConsoleHelpers::log("Ошибка постбека: $updateLinkHref $content", 31);
            }
        } catch (GuzzleException $e) {
            $this->log("Postback Error", __DIR__ . '/../../logs/errors.txt');
            ConsoleHelpers::log("Postback Error", 31);
        }
    }

    /**
     * @param $subId
     * @return bool
     */
    public function getNewLink($subId)
    {
        try {
            $xpartners = new XpartnersBot($this->xpartnersConfig);
            $xpartners->login();
            $json = $xpartners->generateNewLink($subId);

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

$params = require __DIR__ . '/../config/params.php';

foreach ($params as $param) {
    if (!$checkLink->checkLink($param['link'])) {
        $checkLink->postback($checkLink->getNewLink($param['subId']), $param['updateLinkHref']);
    } else {
        $checkLink->log("Ссылка " . $param['link'] . " работает", __DIR__ . '/../../logs/success.txt');
        ConsoleHelpers::log("Ссылка " . $param['link'] . " работает", 32);
    }
}
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
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

include __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../../src/Autoloader.php';

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

            $content = $client->request('POST', $this->config['params']['updateLinkHref'], [
                RequestOptions::FORM_PARAMS => [
                    'new_link' => $newLink,
                ],
            ])->getBody()->getContents();

            if ($content == "OK") {

                // Логируем
                $log = new Logger('postback');

                try {
                    $log->pushHandler(new StreamHandler(__DIR__ . '/../../logs/postback.txt', Logger::INFO));
                } catch (\Exception $e) {
                    ConsoleHelpers::log($e->getMessage(), 31);
                }

                $log->info('Отправили постбек', [
                    'new_link' => $newLink,
                ]);

                ConsoleHelpers::log("Успешно обновили ссыылку", 32);
            } else {

                // Логируем ошибку
                $log = new Logger('postback');

                try {
                    $log->pushHandler(new StreamHandler(__DIR__ . '/../../logs/errors.txt', Logger::ERROR));
                } catch (\Exception $e) {
                    ConsoleHelpers::log($e->getMessage(), 31);
                }

                $log->info('Ошибка при отправке постбека', [
                    'new_link' => $newLink,
                ]);

                ConsoleHelpers::log("Ошибка постбека: " . $content, 31);
            }
        } catch (GuzzleException $e) {
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
}

$checkLink = new CheckLink();

if (!$checkLink->checkLink()) {
    $checkLink->postback($checkLink->getNewLink());
} else {
    ConsoleHelpers::log("Ссылка работает", 32);
}
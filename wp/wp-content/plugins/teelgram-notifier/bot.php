<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
$bot = new \TelegramBot\Api\Client('438332110:AAFCgeVIz_vq6HJznmLqbvTcxbZ0v4lCEzY');
//Handling commands from the user
$bot->command('start', function ($message) use ($bot) {
    $text = 'Hello, thank`s for subscribing. Commands list: /help';
    $bot->sendMessage($message->getChat()->getId(), $text);
    $bot->run();
});
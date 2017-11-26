<?php

/**
 * Plugin Name: Telegram Notifier
 * Plugin URI: some href
 * Description: <strong>some description</strong>
 * Version: 0.1
 * Author: Stos Dima
 * Author URI: stosdima@gmail.com
 * License: MIT
 */

if (!defined('ABSPATH')) {
    //If wordpress isn't loaded load it up.
    $path = $_SERVER['DOCUMENT_ROOT'];
    include_once $path . '/wp/wp-load.php';
}
define('TELEGRAM_NOTIFIER_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!function_exists('add_action')) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
require_once(TELEGRAM_NOTIFIER_PLUGIN_DIR . 'class.helper.php');
require_once(TELEGRAM_NOTIFIER_PLUGIN_DIR . 'class.telegram-menu.php');
require_once(TELEGRAM_NOTIFIER_PLUGIN_DIR . 'class.telegram-db.php');
require_once(TELEGRAM_NOTIFIER_PLUGIN_DIR . 'class.telegram-bot.php');

if (is_admin()) {
    $db = new Telegram_Db();
    register_activation_hook(__FILE__, [$db, 'create_table']);
    register_deactivation_hook(__FILE__, [$db, 'delete_table']);
    $my_settings_page = new Telegram_Menu();
    $bot_send_msg = new Telegram_Bot();
}
$bot = new \TelegramBot\Api\Client($this->options['bot_token']);
//Handling commands from the user
$bot->command('start', function ($message) use ($bot) {
    $text = 'Hello, thank`s for subscribing. Commands list: /help';
    $bot->sendMessage($message->getChat()->getId(), $text);
    $bot->run();
});



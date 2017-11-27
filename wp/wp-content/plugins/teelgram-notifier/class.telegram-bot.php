<?php
/**
 * Telegram_Bot Class
 *
 * @version 0.2
 */
class Telegram_Bot
{
    /**
     * Init options from wordpress database
     * @var $options
     */
    private $options;

    /**
     * Init offset for long polling method
     * @var $offset
     */
    private $offset;

    /**
     * Init TelegamBot API SDK
     * @var $api
     */
    private $api;

    /**
     * Telegram_Bot constructor.*
     * <code>$this->options</code> getting options from wordpress
     * <code>$this->api</code> adding TelegramBot object
     */
    public function __construct()
    {
        $this->options = get_option('telegram_bot_options');
        if ($this->options['bot_token']) {
            $this->api = new TelegramBot\Api\BotApi($this->options['bot_token']);
            add_action('draft_to_publish', [$this, 'send_post_to_telegram_users']);
            if ($_SERVER["SERVER_ADDR"] == '127.0.0.1' || !is_ssl()) {
                add_action('init', [$this, 'long_poll_chat_commands_responce']);
            } else {
                add_action('init', [$this, 'setWebhook']);
            }
        }else {
            global $error;
            $error = new WP_Error('option_empty', 'BOT_TOKEN cant be empty');
        }
    }

    /**
     * Send new post to telegram users
     */
    public function send_post_to_telegram_users()
    {
        $helper = new Helper();
        $db = new Telegram_Db();
        $recent_post = wp_get_recent_posts(['numberposts' => 1]);
        foreach ($db->chatAll() as $id) {
            foreach ($recent_post as $post) {
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [
                            ['text' => 'Show at the site', 'url' => get_permalink($post['ID'])]
                        ]
                    ]
                );
                $text = $helper->generate_telegram_post(get_permalink($post['ID']), $post['post_title'],
                    $post['post_content']);
                $this->api->sendMessage($id->chat_id, $text, 'html', false, null, $keyboard);
            }
        }
    }

    /**
     * Long polling techology for sites with disabled SSL(may used on localhost environment) @link https://core.telegram.org/bots/api#getupdates
     * Processing users messages coming from Telegram application(Every step writes in database)
     * Getting updates from Telegram Bot API <code>$this->api->getUpdates() </code>
     * @see BotApi
     */
    public function long_poll_chat_commands_responce()
    {
        try {
            $helper = new Helper();
            $db = new Telegram_Db();
            $this->offset = 0;
            $response = $this->api->getUpdates($this->offset, 60);
            foreach ($response as $data) {
                if ($data->getMessage()) {
                    //get params from telegram responce
                    $chatId = $data->getMessage()->getChat()->getId();
                    $firstName = $data->getMessage()->getChat()->getFirstName();
                    $lastName = $data->getMessage()->getChat()->getLastName();
                    $status = $db->getStatus($chatId);
                    //Handling commands from the telegram bot user
                    switch ($data->getMessage()->getText()) {
                        case '/start':
                            $db->addContact($chatId);
                            $text = 'Hello, thank`s for subscribing. Commands list: /help';
                            $db->resetStatus($chatId);
                            $this->api->sendMessage($chatId, $text, null, false, null, null);
                            break;
                        case '/stop':
                            $db->deleteContact($chatId);
                            $text = 'You have been deleted from bot database. If you want start again, please, send me /start';
                            $this->api->sendMessage($chatId, $text);
                            break;
                        case '/search':
                            $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                                [
                                    [
                                        ['text' => 'Categories', 'callback_data' => 'categories'],
                                        ['text' => 'Keyword', 'callback_data' => 'search-keyword'],
                                    ]
                                ]
                            );
                            $db->updateStatus($chatId, 'search-keyword');
                            $text = 'Search by: ';
                            $this->api->sendMessage($chatId, $text, 'html', false, null, $keyboard);
                            break;
                        case '/admin':
                            if (!$db->isAdmin($chatId)) {
                                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                                    [
                                        [
                                            ['text' => 'Login', 'callback_data' => 'login'],
                                        ]
                                    ]
                                );
                            } else {
                                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                                    [
                                        [
                                            ['text' => 'Create Post', 'callback_data' => 'post-create'],
                                            ['text' => 'Delete Post', 'callback_data' => 'post-delete'],
                                            ['text' => 'User statistic', 'callback_data' => 'statistic'],
                                        ]
                                    ]
                                );
                            }
                            $db->updateStatus($chatId, 'admin');
                            $text = 'Welcome, ' . $firstName . ' ' . $lastName . '  you are in admin panel.';
                            $this->api->sendMessage($chatId, $text, 'html', false, null, $keyboard);
                            break;
                        case '/help':
                            $text =
                                'List of commands:
                        /start - start work with bot 
                        /stop - stop work with bot
                        /search - search posts by categories
                        /admin - site administrator panel
                        <b>if you want get quote input random message</b>';
                            $this->api->sendMessage($chatId, $text, 'html');
                            break;
                        default:
                            if ($status && $status[0]->status == 'start') {
                                $text = '<b>I`m not chat bot. You can read some quote:)</b>' . "<i>{$helper->get_quote()}</i>";
                                $this->api->sendMessage($chatId, $text, 'html');
                            }
                            break;
                    }
                    //processing response to a message using the current status of the user in the database
                    if ($status) {
                        switch ($status[0]->status) {
                            case 'admin-verif':
                                if ($this->options['verif_code'] == $data->getMessage()->getText()) {
                                    $text = 'Yo are logged in. Thanks!';
                                    $this->api->sendMessage($chatId, $text);
                                    $db->updateAdmin($chatId);
                                    $db->resetStatus($chatId);
                                } else {
                                    $text = 'Incorrect verification code. Please re-type: ';
                                    $this->api->sendMessage($chatId, $text);
                                }
                                break;
                            case 'admin-post':
                                $postContent = explode('::', $data->getMessage()->getText());
                                if (count($postContent) == 2) {
                                    $postData = [
                                        'post_status' => 'publish',
                                        'post_author' => 1,
                                        'post_title' => $postContent[0],
                                        'post_content' => $postContent[1],
                                    ];
                                    $text = 'You are awesome! <b>Post was created</b>';
                                    $this->api->sendMessage($chatId, $text, 'html');
                                    $newPost = wp_insert_post($postData);
                                    foreach ($db->chatAll() as $id) {
                                        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                                            [
                                                [
                                                    ['text' => 'Show at the site', 'url' => get_permalink($newPost)]
                                                ]
                                            ]
                                        );
                                        $text = $helper->generate_telegram_post(get_permalink($postData['ID']),
                                            $postData['post_title'], $postData['post_content']);
                                        $this->api->sendMessage($id->chat_id, $text, 'html', false, null, $keyboard);
                                    }
                                    $db->updateStatus($chatId, 'start');
                                } else {
                                    $text = 'Incorrect delimiter, please re-type <b>data( example - TITLE :: BODY)</b>';
                                    $this->api->sendMessage($chatId, $text, 'html');
                                }
                                break;
                            case 'admin-post-delete':
                                if (wp_delete_post($data->getMessage()->getText())) {
                                    $text = 'Post was deleted.';
                                    $this->api->sendMessage($chatId, $text);
                                    $db->resetStatus($chatId);
                                } else {
                                    $text = 'Error. Please re-type Post ID:';
                                    $this->api->sendMessage($chatId, $text);
                                }
                                break;
                            case 'search-keyword':
                                $posts = $db->searchByKeyword($data->getMessage()->getText());
                                if ($posts) {
                                    $text = '';
                                    foreach ($posts as $post) {
                                        $text .= $helper->generate_telegram_post(get_permalink($post->ID),
                                                $post->post_title, $post->post_content) . "\n";
                                    }
                                    $this->api->sendMessage($chatId, $text, 'html', false, null);
                                    $db->resetStatus($chatId);
                                } else {
                                    $text = 'The search did not give a result.';
                                    $this->api->sendMessage($chatId, $text);
                                    $db->resetStatus($chatId);
                                }
                                break;
                        }
                    }
                }
                //processing of button presses
                if ($data->getCallbackQuery()) {
                    $callbackId = $data->getCallbackQuery()->getMessage()->getChat()->getId();
                    switch ($data->getCallbackQuery()->getData()) {
                        case 'categories':
                            if ($helper->get_categories_buttons_list()) {
                                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($helper->get_categories_buttons_list());
                                $text = 'List of categories: ';
                                $db->updateStatus($callbackId, 'search-categories');
                                $this->api->sendMessage($callbackId, $text, null, false, null, $keyboard);
                            } else {
                                $text = 'At that moment you haven`t created categories ';
                                $this->api->sendMessage($callbackId, $text, null, false, null, null);
                                $db->resetStatus($callbackId);
                            }
                            break;
                        case 'login':
                            $text = 'Insert <b>verification code</b> from your site: ';
                            $this->api->sendMessage($callbackId, $text, 'html');
                            $db->updateStatus($callbackId, 'admin-verif');
                            break;
                        case 'post-create':
                            $text = 'Send me, please, your post <b>data( example - TITLE :: BODY)</b>: ';
                            $db->updateStatus($callbackId, 'admin-post');
                            $this->api->sendMessage($callbackId, $text, 'html');
                            break;
                        case 'search-keyword':
                            $text = 'Please, type <b>keyword</b> and you will get list of posts:';
                            $db->updateStatus($callbackId, 'search-keyword');
                            $this->api->sendMessage($callbackId, $text, 'html');
                            break;
                        case 'post-delete':
                            $posts = get_posts(['numberposts' => 0]);
                            if ($posts) {
                                $text = 'Please choose post ID which you want to delete from list below: ' . "\n";
                                foreach ($posts as $post) {
                                    $text .= $helper->generate_telegram_post(get_permalink($post->ID), $post->post_title,
                                            'ID -> ' . $post->ID) . "\n";
                                }
                                $db->updateStatus($callbackId, 'admin-post-delete');
                                $this->api->sendMessage($callbackId, $text, 'html');
                            } else {
                                $text = 'You havent created posts :(';
                                $db->resetStatus($callbackId);
                                $this->api->sendMessage($callbackId, $text);
                            }
                            break;
                        case 'statistic':
                            $allusers = count($db->chatAll());
                            $text = 'Current users: ' . $allusers;
                            $db->resetStatus($callbackId);
                            $this->api->sendMessage($callbackId, $text);
                            break;
                    }
                    // compare callback_data(from categories search) and category ID
                    foreach (get_categories() as $category) {
                        if ($data->getCallbackQuery()->getData() == $category->term_id) {
                            $posts = get_posts(['category' => $category->term_id]);
                            $text = '';
                            if ($posts) {
                                foreach ($posts as $post) {
                                    $text .= $helper->generate_telegram_post(get_permalink($post->ID), $post->post_title,
                                            $post->post_content) . "\n";
                                }
                                $this->api->sendMessage($callbackId, $text, 'html');
                                $db->resetStatus($callbackId);
                            } else {
                                $text = 'There are no posts in this category';
                                $this->api->sendMessage($callbackId, $text);
                            }
                        }
                    }
                }
                $this->offset = $response[count($response) - 1]->getUpdateId() + 1;
            }
            $response = $this->api->getUpdates($this->offset, 60);
        } catch (\TelegramBot\Api\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Setting webhook @link https://core.telegram.org/bots/api#setwebhook
    */
    public function setWebhook()
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'telegram-settings') {
            $pluginUrl = plugins_url('teelgram-notifier/telegram-notifier.php');
            try {
                $this->api->setWebhook($pluginUrl);
            } catch (\TelegramBot\Api\Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    /**
     * Webhook technology for for sites with enabled SSL. Can`t be used on localhost environment
     * Processing users messages coming from Telegram application
     * @see Client
     */
    public function webhook_chat_command_responce()
    {
        try {
            $bot = new \TelegramBot\Api\Client($this->options['bot_token']);
            $db = new Telegram_Db();
            $helper = new Helper();
            //Handling commands from the user
            $bot->command('start', function ($message) use ($bot, $db) {
                $db->addContact($message->getChat()->getId());
                $db->resetStatus($message->getChat()->getId());
                $text = 'Hello, thank`s for subscribing. Commands list: /help';
                $bot->sendMessage($message->getChat()->getId(), $text);
            });
            $bot->command('help', function ($message) use ($bot) {
                $commandList = 'List of commands:
                         /start - start work with bot
                         /stop - stop work with bot
                         /search - search posts by categories
                         /admin - site administrator panel
                         if you want get quote input random message';
                $bot->sendMessage($message->getChat()->getId(), $commandList);
            });
            $bot->command('search', function ($message) use ($bot, $db) {
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [
                            ['text' => 'Categories', 'callback_data' => 'categories'],
                            ['text' => 'Keyword', 'callback_data' => 'keyword']
                        ]
                    ]
                );
                $db->updateStatus($message->getChat()->getId(), 'search-keyword');
                $text = 'Search by: ';
                $bot->sendMessage($message->getChat()->getId(), $text, null, false, null, $keyboard);
            });
            $bot->command('admin', function ($message) use ($bot, $db) {
                if (!$db->isAdmin($message->getChat()->getId())) {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [
                                ['text' => 'Login', 'callback_data' => 'login'],
                            ]
                        ]
                    );
                } else {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [
                                ['text' => 'Create Post', 'callback_data' => 'post-create'],
                                ['text' => 'Delete Post', 'callback_data' => 'post-delete'],
                                ['text' => 'User statistic', 'callback_data' => 'statistic'],
                            ]
                        ]
                    );
                }
                $db->updateStatus($message->getChat()->getId(), 'admin');
                $text = 'Welcome, ' . $message->getChat()->getFirstName() . ' ' . $message->getChat()->getLastName . '  you are in admin panel.';
                $bot->sendMessage($message->getChat()->getId(), $text, null, false, null, $keyboard);
            });
            $bot->command('stop', function ($message) use ($bot, $db) {
                $db->deleteContact($message->getChat()->getId());
                $text = 'You have been deleted from bot database. If you want start again, please, send me /start';
                $bot->sendMessage($message->getChat()->getId(), $text);
            });
            //processing of button presses
            $bot->callbackQuery(function (\TelegramBot\Api\Types\CallbackQuery $callbackQuery) use ($bot, $helper, $db) {
                $callbackId = $callbackQuery->getFrom()->getId();
                switch ($callbackQuery->getData()) {
                    case 'categories':
                        $helper = new Helper();
                        if ($helper->get_categories_buttons_list()) {
                            $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($helper->get_categories_buttons_list());
                            $db->updateStatus($callbackId, 'search-categories');
                            $text = 'List of categories: ';
                            $bot->sendMessage($callbackId, $text, null, false, null, $keyboard);
                        } else {
                            $db->resetStatus($callbackId);
                            $text = 'At that moment you haven`t created categories ';
                            $bot->sendMessage($callbackId, $text, null, false, null, null);
                        }
                        break;
                    case 'login':
                        $db->updateStatus($callbackId, 'admin-verif');
                        $text = 'Insert <b>verification code</b> from your site: ';
                        $bot->sendMessage($callbackId, $text, 'html');
                        break;
                    case 'post-create':
                        $db->updateStatus($callbackId, 'admin-post');
                        $text = 'Send me, please, your post <b>data( example - TITLE :: BODY)</b>: ';
                        $bot->sendMessage($callbackId, $text, 'html');
                        break;
                    case 'keyword':
                        $db->updateStatus($callbackId, 'search-keyword');
                        $text = 'Please, type <b>keyword</b> and you will get list of posts:';
                        $bot->sendMessage($callbackId, $text, 'html');
                        break;
                    case 'post-delete':
                        $posts = get_posts(['numberposts' => 0]);
                        if ($posts) {
                            $text = 'Please choose post ID which you want to delete from list below: ' . "\n";
                            foreach ($posts as $post) {
                                $text .= $helper->generate_telegram_post(get_permalink($post->ID), $post->post_title,
                                        'ID -> ' . $post->ID) . "\n";
                            }
                            $db->updateStatus($callbackId, 'admin-post-delete');
                            $bot->sendMessage($callbackId, $text, 'html');
                        } else {
                            $db->resetStatus($callbackId);
                            $text = 'You havent created posts :(';
                            $bot->sendMessage($callbackId, $text);
                        }
                        break;
                    case 'statistic':
                        $db->resetStatus($callbackId);
                        $allusers = count($db->chatAll());
                        $text = 'Current users: ' . $allusers;
                        $bot->sendMessage($callbackId, $text);
                        break;
                }
                foreach (get_categories() as $category) {
                    if ($callbackQuery->getData() == $category->term_id) {
                        $posts = get_posts(['category' => $category->term_id]);
                        $text = '';
                        if ($posts) {
                            foreach ($posts as $post) {
                                $text .= $helper->generate_telegram_post(get_permalink($post->ID), $post->post_title,
                                        $post->post_content) . "\n";
                            }
                            $bot->sendMessage($callbackId, $text, 'html');
                            $db->resetStatus($callbackId);
                        } else {
                            $text = 'There are no posts in this category';
                            $bot->sendMessage($callbackId, $text);
                        }
                    }
                }
            });
            //processing response to a message using the current status of the user in the database
            $bot->on(function (\TelegramBot\Api\Types\Update $update) use ($bot, $helper, $db) {
                //getting params from webhook updates
                $message = $update->getMessage();
                $userText = $message->getText();
                $chat_id = $message->getChat()->getId();
                $status = $db->getStatus($chat_id);
                if($status) {
                    switch ($status[0]->status) {
                        case 'admin-verif':
                            if ($this->options['verif_code'] == $userText) {
                                $text = 'Yo are logged in. Thanks!';
                                $db->updateAdmin($chat_id);
                                $db->resetStatus($chat_id);
                                $bot->sendMessage($chat_id, $text);
                            } else {
                                $text = 'Incorrect verification code. Please re-type: ';
                                $bot->sendMessage($chat_id, $text);
                            }
                            break;
                        case 'admin-post':
                            $postContent = explode('::', $userText);
                            if (count($postContent) == 2) {
                                $postData = [
                                    'post_status' => 'publish',
                                    'post_author' => 1,
                                    'post_title' => $postContent[0],
                                    'post_content' => $postContent[1],
                                ];
                                $text = 'You are awesome! <b>Post was created</b>';
                                $bot->sendMessage($chat_id, $text, 'html');
                                $newPost = wp_insert_post($postData);
                                foreach ($db->chatAll() as $id) {
                                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                                        [
                                            [
                                                ['text' => 'Show at the site', 'url' => get_permalink($newPost)]
                                            ]
                                        ]
                                    );
                                    $text = $helper->generate_telegram_post(get_permalink($postData['ID']), $postData['post_title'], $postData['post_content']);
                                    $bot->sendMessage($id->chat_id, $text, 'html', false, null, $keyboard);
                                }
                                $db->updateStatus($chat_id, 'start');
                            } else {
                                $text = 'Incorrect delimiter, please re-type <b>data( example - TITLE :: BODY)</b>';
                                $bot->sendMessage($chat_id, $text, 'html');
                            }
                            break;
                        case 'admin-post-delete':
                            if (wp_delete_post($userText)) {
                                $text = 'Post was deleted.';
                                $bot->sendMessage($chat_id, $text);
                                $db->resetStatus($chat_id);
                            } else {
                                $text = 'Error. Please re-type Post ID:';
                                $bot->sendMessage($chat_id, $text);
                            }
                            break;
                        case 'search-keyword':
                            $posts = $db->searchByKeyword($userText);
                            if ($posts) {
                                $text = '';
                                foreach ($posts as $post) {
                                    $text .= $helper->generate_telegram_post(get_permalink($post->ID),
                                            $post->post_title, $post->post_content) . "\n";
                                }
                                $bot->sendMessage($chat_id, $text, 'html', false, null);
                                $db->resetStatus($chat_id);
                            } else {
                                $text = 'The search did not give a result.';
                                $bot->sendMessage($chat_id, $text);
                                $db->resetStatus($chat_id);
                            }
                            break;
                    }
                }
            }, function ($update){
                return true;
            });
            $bot->run();
        } catch (\TelegramBot\Api\Exception $e) {
            $e->getMessage();
        }
    }
}
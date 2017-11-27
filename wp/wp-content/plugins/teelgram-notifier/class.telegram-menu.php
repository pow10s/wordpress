<?php
/**
 * Telegram_Menu Class
 * @version 0.1.0
 */
class Telegram_Menu
{
    private $options;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_plugin_page']);
        add_action('admin_init', [$this, 'page_init']);
    }

    public function add_plugin_page()
    {
        add_menu_page(
            'Settings Telegram',
            'Telegram Settings',
            'manage_options',
            'telegram-settings',
            [$this, 'create_admin_page'],
            plugin_dir_url(__FILE__) . '/img/telegram-icon.png'
        );
    }

    public function create_admin_page()
    {
        $this->options = get_option('telegram_bot_options');
        if ($_SERVER["SERVER_ADDR"] == '127.0.0.1' || !is_ssl()) {
            echo '<div class="notice notice-error is-dismissible">
            <p>' . __('Warning: the WebHooks is not working while you are in a localhost environment or SSL not active.') . '</p>
            </div>';
        }else {
            echo '<div class="notice notice-success is-dismissible">
            <p>' . __('Webhook active') . '</p>
            </div>';
        }
        ?>
        <div class="wrap">
            <h1> Telegram notifier settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('telegram_bot_option_group');
                do_settings_sections('telegram-setting-admin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init()
    {
        register_setting(
            'telegram_bot_option_group', // Option group
            'telegram_bot_options', // Option name
            [$this, 'sanitize'] // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            '', // Title
            [$this, 'print_section_info'], // Callback
            'telegram-setting-admin' // Page
        );

        add_settings_field(
            'bot_token', // ID
            'Bot token: ', // Title
            [$this, 'bot_token_callback'], // Callback
            'telegram-setting-admin', // Page
            'setting_section_id' // Section
        );
        add_settings_field(
            'verif_code', // ID
            'Telegam verification code: ', // Title
            [$this, 'verif_code_callback'], // Callback
            'telegram-setting-admin', // Page
            'setting_section_id' // Section
        );
    }

    public function sanitize($input)
    {
        $new_input = [];
        if (isset($input['bot_token']) && !empty($input['bot_token'])) {
            $new_input['bot_token'] = sanitize_text_field($input['bot_token']);
        }
        if (isset($input['verif_code'])) {
            $new_input['verif_code'] = sanitize_text_field($input['verif_code']);
        }
        return $new_input;
    }

    public function print_section_info()
    {
        print 'Enter your settings below: ';
        global $error;
        if ( $error->get_error_code() ) {
            foreach( $error->get_error_messages() as $errr ){
                echo '<div><strong>Ошибка</strong>:'. $errr .'</div>';
            }
        }

    }

    public function bot_token_callback()
    {
        printf(
            '<input type="text" id="bot_token" name="telegram_bot_options[bot_token]" value="%s" />',
            isset($this->options['bot_token']) ? esc_attr($this->options['bot_token']) : ''
        );
    }

    public function verif_code_callback()
    {
        $helper = new Helper();
        printf(
            '<input readonly="readonly" type="text" id="verif_code" name="telegram_bot_options[verif_code]" value="%s" />',
            isset($this->options['verif_code']) ? esc_attr($this->options['verif_code']) : $helper->randomString()
        );
    }

}
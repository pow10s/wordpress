<?php
/**
 * Telegram_Db Class
 * @version 0.1.0
 */
class Telegram_Db
{
    /**
     * Creating table in database
     */
    public function create_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'telegram_users';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				chat_id VARCHAR(100) NOT NULL,
				status VARCHAR(25) NOT NULL,
				is_admin tinyint DEFAULT 0 NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Deleting table in database
     * @return  false|int
     */
    public function delete_table()
    {
        global $wpdb;
        return $wpdb->query("DROP TABLE IF EXISTS wp_telegram_users");
    }

    /**
     * Adding user in database
     * @param int $chatId
     */
    public function addContact($chatId)
    {
        global $wpdb;
        $chat = $wpdb->get_results("
        SELECT chat_id 
        FROM wp_telegram_users
        WHERE chat_id = $chatId
        ");
        if (!$chat) {
            $wpdb->insert($wpdb->prefix . 'telegram_users', ['chat_id' => $chatId, 'status' => 'start'], ['%s', '%s']);
        }
    }

    /**
     * Deleting user form database
     * @param $chatId
     * @return array|null|object
     */
    public function deleteContact($chatId)
    {
        global $wpdb;
        $chat = $wpdb->get_results("
        DELETE FROM " . $wpdb->prefix . "telegram_users 
        WHERE chat_id = $chatId
        ");
        return $chat;
    }

    /**
     * Update user current status
     * @param $chatId
     * @param $status
     * @return false|int
     */
    public function updateStatus($chatId, $status)
    {
        global $wpdb;
        $chat = $wpdb->update($wpdb->prefix . 'telegram_users', ['status' => $status], ['chat_id' => $chatId], ['%s'],
            ['%s']);
        return $chat;
    }

    /**
     * Reset user current status
     * @param $chatId
     * @return false|int
     */
    public function resetStatus($chatId)
    {
        global $wpdb;
        $chat = $chat = $wpdb->update($wpdb->prefix . 'telegram_users', ['status' => 'start'], ['chat_id' => $chatId],
            ['%s'], ['%s']);

        return $chat;
    }

    /**
     * Get all users in database
     * @return array|null|object
     */
    public function chatAll()
    {
        global $wpdb;
        $chats = $wpdb->get_results("SELECT * FROM wp_telegram_users");
        return $chats;
    }

    /**
     * Get current user status
     * @param $chatId
     * @return array|null|object
     */
    public function getStatus($chatId)
    {
        global $wpdb;
        $chats = $wpdb->get_results("SELECT * FROM wp_telegram_users WHERE chat_id = $chatId");
        return $chats;
    }

    /**
     * Updating admin status for user
     * @param $chatId
     * @return false|int
     */
    public function updateAdmin($chatId)
    {
        global $wpdb;
        $chat = $wpdb->update($wpdb->prefix . 'telegram_users', ['is_admin' => '1'], ['chat_id' => $chatId], ['%s'],
            ['%s']);
        return $chat;
    }

    /**
     * Checing if user is admin
     * @param $chatId
     * @return bool
     */
    public function isAdmin($chatId)
    {
        global $wpdb;
        $chat = $wpdb->get_results("
        SELECT is_admin 
        FROM wp_telegram_users
        WHERE chat_id = $chatId
        ");
        return ($chat[0]->is_admin == 1) ? true : false;
    }

    /**
     * Get posts by keyword
     * @param $keyword
     * @return array
     */
    public function searchByKeyword($keyword)
    {
        $query = new WP_Query(['s' => $keyword]);
        return $query->get_posts();
    }
}
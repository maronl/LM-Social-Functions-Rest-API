<?php
/**
 * Created by PhpStorm.
 * User: maronl
 * Date: 11/10/17
 * Time: 10:22
 */

namespace LM\WPPostLikeRestApi\Repository;


class LMLikePostWordpressRepository implements LMLikePostRepository
{
    private $table;

    private $version;

    private $tableNoPrefix;

    function __construct($tableName, $version)
    {
        global $wpdb;
        $this->table = $wpdb->prefix . $tableName;
        $this->tableNoPrefix = $tableName;
        $this->version = $version;
    }

    public function saveLike($userId, $postId)
    {
        global $wpdb;

        $data = array(
            'user_id' => $userId,
            'post_id' => $postId,
            'created_at' => date('Y-m-d H:i:s')
        );

        $format = array('%d', '%d', '%s');

        return $wpdb->replace($this->table, $data, $format);
    }

    public function deleteLike($userId, $postId)
    {
        global $wpdb;

        $data = array(
            'user_id' => $userId,
            'post_id' => $postId
        );

        $format = array('%d', '%d');

        return $wpdb->delete($this->table, $data, $format);
    }

    public function findLike($userId, $postId)
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM $this->table WHERE post_id = %d AND user_id = %d", $postId,
            $userId);

        return $wpdb->get_var($sql);
    }

    public function findUserLikePost($postId)
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT u.ID, u.user_login, u.display_name, u.user_email, u.user_registered, u.user_status
            FROM " . $this->table. " AS l 
              INNER JOIN " . $wpdb->prefix . "users as u
                ON l.user_id = u.ID AND l.post_id = %d;", $postId);

        $users = $wpdb->get_results($sql);

        if (has_filter('lm-sf-rest-api-get-user-like-post')) {
            $users = apply_filters('lm-sf-rest-api-get-user-like-post', $users);
        }

        return $users;

    }

    public function findPostIdsLikeByUser($userId)
    {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT post_id 
            FROM " . $this->table. "  
            WHERE user_id = %d ORDER BY created_at DESC;", $userId);

        $users = $wpdb->get_results($sql, ARRAY_N);

        $users = array_reduce($users, 'array_merge', array());

        return $users;

    }


    public function getTableName()
    {
        return $this->table;
    }

    public function createDBStructure()
    {
        global $wpdb;

        $tableName = $this->table;

        $charset_collate = '';

        if (!empty($wpdb->charset)) {
            $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
        }

        if (!empty($wpdb->collate)) {
            $charset_collate .= " COLLATE {$wpdb->collate}";
        }

        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
          user_id BIGINT(11) NOT NULL,
          post_id BIGINT(11) NOT NULL,
          created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
          PRIMARY KEY (user_id, post_id),
          KEY `".$this->tableNoPrefix."_created_at` (`created_at`)
	    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);

        add_option($this->tableNoPrefix . '_db_version', $this->version);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: maronl
 * Date: 11/10/17
 * Time: 10:04
 */

namespace LM\WPPostLikeRestApi\Service;


class LMWallWordpressService implements LMWallService
{

    public function getWall(Array $params)
    {
        $paramsQuery = $this->setWallQueryParameters($params);
        return $this->completePostsInformation(get_posts($paramsQuery));
    }

    private function setWallQueryParameters(Array $params)
    {
        $paramsQuery = array();

        if(array_key_exists('item_per_page', $params)) {
            $paramsQuery['posts_per_page'] = $params['item_per_page'];
        }

        if(array_key_exists('page', $params)) {
            $paramsQuery['offset'] = ($params['page'] - 1) * $params['item_per_page'];
        }

        if(array_key_exists('categories', $params)) {
            $paramsQuery['cat'] = $params['categories'];
        }

        if(array_key_exists('authors', $params)) {
            $paramsQuery['author'] = $params['authors'];
        }

        return $paramsQuery;
    }

    private function completePostsInformation(Array $posts = array())
    {
        if(empty($posts)) {
            return array();
        }

        $res = array();

        foreach ($posts as $post) {
            $res[] = $this->retrievePostInformation($post);
        }

        return $res;
    }

    private function retrievePostInformation(\WP_Post $post)
    {
        global $wpdb;

        $post->post_content_rendered = apply_filters('the_content', $post->post_content);

        $post->post_excerpt_rendered = apply_filters('the_excerpt', $post->post_excerpt);

        $post->author = $this->retrieveAuthorInformation($post, $wpdb);

        $post->latest_comment = get_comments(array('post_id' => $post->ID, 'number' => 2, 'orderby' => 'comment_date', 'order' => 'DESC'));

        $post->latest_comment = array_reverse($post->latest_comment );

        $post->categories = get_the_terms($post->ID, 'category');

        $post->lm_like_counter = $this->retrieveLikeCounter($post);

        $post->lm_saved_counter = $this->retrieveSavedCounter($post);

        $post->featured_image = get_the_post_thumbnail_url($post->ID);

        return $post;
    }

    /**
     * @param \WP_Post $post
     * @param $wpdb
     */
    private function retrieveAuthorInformation(\WP_Post $post, $wpdb)
    {
        $sql = $wpdb->prepare("SELECT u.ID, u.user_login, u.display_name, u.user_email, u.user_registered, u.user_status
            FROM pld_users as u
            WHERE u.ID = %d;", $post->post_author);

        return $wpdb->get_row($sql);
    }

    /**
     * @param \WP_Post $post
     * @return int
     */
    private function retrieveLikeCounter(\WP_Post $post)
    {
        $like_counter = get_post_meta($post->ID, 'lm-like-counter', true);
        if (empty($like_counter)) {
            $like_counter = 0;
        }
        return $like_counter;
    }

    /**
     * @param \WP_Post $post
     * @return int
     */
    private function retrieveSavedCounter(\WP_Post $post)
    {
        $saved_counter = get_post_meta($post->ID, 'lm-saved-counter', true);
        if (empty($saved_counter)) {
            $saved_counter = 0;
        }
        return $saved_counter;
    }


}
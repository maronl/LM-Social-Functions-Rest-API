<?php
/**
 * Created by PhpStorm.
 * User: maronl
 * Date: 11/10/17
 * Time: 10:04
 */

namespace LM\WPPostLikeRestApi\Service;


use LM\WPPostLikeRestApi\Repository\LMWallPostWordpressRepository;
use LM\WPPostLikeRestApi\Request\LMWallPostInsertRequest;
use LM\WPPostLikeRestApi\Request\LMWallPostUpdateRequest;
use LM\WPPostLikeRestApi\Utility\LMHeaderAuthorization;
use LM\WPPostLikeRestApi\Utility\LMWPPostWallDetails;

class LMWallWordpressService implements LMWallService
{

    use LMWPPostWallDetails;

    /**
     * @var LMHeaderAuthorization
     */
    private $headerAuthorization;
    /**
     * @var LMLikePostService
     */
    private $likePostService;
    /**
     * @var LMLikePostService
     */
    private $savedPostService;
    /**
     * @var LMFollowerService
     */
    private $followerService;
    /**
     * @var LMWallPostWordpressRepository
     */
    private $wallPostWordpressRepository;
    /**
     * @var LMWallPostInsertRequest
     */
    private $insertRequest;
    /**
     * @var LMSharingService
     */
    private $sharingService;
    /**
     * @var LMBlockUserService
     */
    private $blockUserService;

    /**
     * LMWallWordpressService constructor.
     * @param LMHeaderAuthorization $headerAuthorization
     * @param LMWallPostWordpressRepository $wallPostWordpressRepository
     * @param LMFollowerService $followerService
     * @param LMLikePostService $likePostService
     * @param LMLikePostService $savedPostService
     * @param LMWallPostInsertRequest $insertRequest
     * @param LMSharingService $sharingService
     * @param LMBlockUserService $blockUserService
     */
    function __construct(
        LMHeaderAuthorization $headerAuthorization,
        LMWallPostWordpressRepository $wallPostWordpressRepository,
        LMFollowerService $followerService,
        LMLikePostService $likePostService,
        LMLikePostService $savedPostService,
        LMWallPostInsertRequest $insertRequest,
        LMSharingService $sharingService,
        LMBlockUserService $blockUserService
    ) {
        $this->headerAuthorization = $headerAuthorization;
        $this->likePostService = $likePostService;
        $this->savedPostService = $savedPostService;
        $this->followerService = $followerService;
        $this->wallPostWordpressRepository = $wallPostWordpressRepository;
        $this->insertRequest = $insertRequest;
        $this->sharingService = $sharingService;
        $this->blockUserService = $blockUserService;
    }

    public function getWall(Array $params)
    {
        $paramsQuery = $this->setWallQueryParameters($params);

        $paramsQuery['suppress_filters'] = false;

        // exclude hidden post
        add_filter('posts_join', array($this, 'filterJoinUserHiddenPost'));
        add_filter('posts_where', array($this, 'filterWhereUserHiddenPost'));
        if(array_key_exists('most_active_order_by', $params) && $params['most_active_order_by']) {
            add_filter('posts_join', array($this, 'filterJoinMostActive'));
            add_filter('posts_orderby', array($this, 'filterOrderByMostActive'));
        }

        // exclude users blocked
        $paramsQuery = $this->excludeBlockedUsersContent($paramsQuery);

        $posts = get_posts($paramsQuery);

        remove_filter('posts_join', array($this, 'filterJoinUserHiddenPost'));
        remove_filter('posts_where', array($this, 'filterWhereUserHiddenPost'));
        if(array_key_exists('most_active_order_by', $params) && $params['most_active_order_by']) {
            remove_filter('posts_join', array(  $this, 'filterJoinMostActive'));
            remove_filter('posts_orderby', array($this, 'filterOrderByMostActive'));
        }

        return $this->completePostsInformation($posts);
    }

    public function getPost($postId)
    {
        $post = get_post($postId);

        if (empty($post)) {
            return false;
        }

        if ($post->post_status !== 'publish') {
            return false;
        }

        return $this->retrievePostInformation($post, 3, $this->likePostService, $this->savedPostService,
            $this->sharingService, $this->wallPostWordpressRepository->getLMWallPostsPictureRepository(),
            $this->wallPostWordpressRepository->getLMWallPostsMovieRepository());
    }

    public function createPost($request)
    {
        $postId = $this->wallPostWordpressRepository->createPost($request);

        if (is_wp_error($postId) || is_array($postId)) {
            return $postId;
        }

        $this->setNewPostFormat($request, $postId);

        $this->setNewPostSharingPost($request, $postId);

        return $postId;
    }

    public function updatePostContent($request)
    {
        $postId = $this->wallPostWordpressRepository->updatePostContent($request);

        if (is_wp_error($postId) || is_array($postId)) {
            return $postId;
        }

        $this->setNewPostFormat($request, $postId);

        return $postId;
    }

    private function setWallQueryParameters(Array $params)
    {
        $paramsQuery = array();

        $paramsQuery['post_type'] = 'lm_wall';

        if (array_key_exists('q', $params)) {
            $paramsQuery['s'] = $params['q'];
        }

        if (array_key_exists('item_per_page', $params)) {
            $paramsQuery['posts_per_page'] = $params['item_per_page'];
        } else {
            $paramsQuery['posts_per_page'] = 20;
        }

        if (array_key_exists('page', $params)) {
            $paramsQuery['offset'] = ($params['page'] - 1) * $params['item_per_page'];
        }

        if (array_key_exists('categories', $params)) {
            $paramsQuery['cat'] = $params['categories'];
        }

        if (array_key_exists('not_in', $params)) {
            $paramsQuery['post__not_in'] = explode(',',$params['not_in']);
        }

        if (array_key_exists('authors', $params)) {
            $paramsQuery['author'] = $params['authors'];
        } elseif (!$this->isRedazioneUser()) {
            // const PLAYDOC_WALL_POST_WITHOUT_FILTER used to remove filters on wall
            if (!defined('PLAYDOC_WALL_POST_WITHOUT_FILTER') || PLAYDOC_WALL_POST_WITHOUT_FILTER !== true) {
                $paramsQuery['author'] = implode(',', $this->getDefaultAuthorsPerUser());
            }
        }

        if (array_key_exists('date_query', $params)) {
            $paramsQuery['date_query'] = $params['date_query'];
        }

        if (array_key_exists('in_wall_category_id', $params)) {
            unset($params['not_in_wall_category_id']);
            $condition = array(
                'taxonomy' => 'lm_wall_category',
                'field' => 'id',
                'terms' => $params['in_wall_category_id'],
                'operator' => 'IN',
            );

            if (!array_key_exists('tax_query', $paramsQuery)) {
                $paramsQuery['tax_query'] = array($condition);
            } else {
                $paramsQuery['tax_query'][] = $condition;
            }

        }

        if (array_key_exists('in_wall_category_slug', $params)) {
            unset($params['not_in_wall_category_id']);
            $condition = array(
                'taxonomy' => 'lm_wall_category',
                'field' => 'slug',
                'terms' => $params['in_wall_category_slug'],
                'operator' => 'IN',
            );

            if (!array_key_exists('tax_query', $paramsQuery)) {
                $paramsQuery['tax_query'] = array($condition);
            } else {
                $paramsQuery['tax_query'][] = $condition;
            }

        }

        if (array_key_exists('not_in_wall_category_id', $params)) {
            $condition = array(
                'taxonomy' => 'lm_wall_category',
                'field' => 'id',
                'terms' => $params['not_in_wall_category_id'],
                'operator' => 'NOT IN',
            );

            if (!array_key_exists('tax_query', $paramsQuery)) {
                $paramsQuery['tax_query'] = array($condition);
            } else {
                $paramsQuery['tax_query'][] = $condition;
            }
        }

        if (array_key_exists('tax_query', $params)) {
            if (!array_key_exists('tax_query', $paramsQuery)) {
                $paramsQuery['tax_query'] = $params['tax_query'];
            } else {
                $paramsQuery['tax_query'] = array_merge($paramsQuery['tax_query'], $params['tax_query']);
            }
        }

        if (array_key_exists('saved_by_user', $params)) {
            $posts = $this->savedPostService->getPostIdsLikeByUser($params['saved_by_user']);
            $paramsQuery['post__in'] = $posts;
        }

        return $paramsQuery;
    }

    private function completePostsInformation(Array $posts = array())
    {
        if (empty($posts)) {
            return array();
        }

        $res = array();

        foreach ($posts as $post) {
            $res[] = $this->retrievePostInformation($post, 3, $this->likePostService, $this->savedPostService,
                $this->sharingService, $this->wallPostWordpressRepository->getLMWallPostsPictureRepository(),
                $this->wallPostWordpressRepository->getLMWallPostsMovieRepository());
        }

        return $res;
    }

    private function getDefaultAuthorsPerUser()
    {
        $authors = [];

        if (defined('PLAYDOC_FORCE_USERS_ID_ON_WALL')) {
            $forceIds = explode(',', PLAYDOC_FORCE_USERS_ID_ON_WALL);
            $authors = array_merge($authors, $forceIds);
        }

        if (defined('PLAYDOC_FORCE_USERS_ROLES_ON_WALL')) {
            $roles = explode(',', PLAYDOC_FORCE_USERS_ROLES_ON_WALL);
            $forceIds = get_users(array('role__in' => $roles, 'fields' => 'ID'));
            $authors = array_merge($authors, $forceIds);
        }

        $userAuthorized = $this->headerAuthorization->getUser();

        $authors = array_merge($authors, array($userAuthorized));

        if ($userAuthorized) {
            $followings = $this->followerService->getFollowingsIds($userAuthorized);
            $authors = array_merge($authors, $followings);
        }

        $authors = array_unique($authors);

        return $authors;
    }

    /**
     * @param $request
     * @param $postId
     * @return bool
     */
    private function setNewPostFormat($request, $postId)
    {
        $dataRequest = $this->insertRequest->getDataFromRequest($request);

        if (array_key_exists('format', $dataRequest)) {
            set_post_format($postId, $dataRequest['format']);
        }

        return true;
    }

    private function setNewPostSharingPost($request, $postId)
    {
        $dataRequest = $this->insertRequest->getDataFromRequest($request);

        if (array_key_exists('shared_post', $dataRequest) && !empty($dataRequest['shared_post'])) {
            $this->sharingService->saveSharing($dataRequest['shared_post'], $postId);
        }

        return true;
    }

    public function filterJoinUserHiddenPost($join)
    {
        global $wpdb;

        $userQuerying = $this->headerAuthorization->getUser();
        $join .= " LEFT JOIN " . $wpdb->prefix . "lm_post_hidden as ph ON ph.post_id = " . $wpdb->prefix . "posts.ID AND ph.user_id = $userQuerying ";
        return $join;
    }

    public function filterJoinMostActive($join)
    {
        global $wpdb;

        $join .= " LEFT JOIN " . $wpdb->prefix . "postmeta as lc ON lc.post_id = " . $wpdb->prefix . "posts.ID AND lc.meta_key = 'lm-like-counter' ";
        return $join;
    }

    public function filterWhereUserHiddenPost($where)
    {
        $where .= " AND ph.created_at IS NULL ";
        return $where;
    }

    public function filterOrderByMostActive($order)
    {
        $order = ' (lc.meta_value + pld_posts.comment_count) DESC,  pld_posts.post_date DESC';

        return $order;
    }

    private function excludeBlockedUsersContent($paramsQuery)
    {
        if (array_key_exists('author', $paramsQuery)) {
            $authors = $paramsQuery['author'];
            $authors = explode(',', $authors);
            $blockedUsers = $this->blockUserService->getBlockedUsers($this->headerAuthorization->getUser());
            $authors = array_diff($authors, $blockedUsers);

            if (empty($authors)) {
                // imposto id utente che non esisterà mai (si spera) per non avere risultati
                $authors[] = 999999999999999999999;
            }

            $paramsQuery['author'] = implode(',', $authors);
            return $paramsQuery;
        }

        return $paramsQuery;
    }

    private function isRedazioneUser()
    {
        // todo id della redazione dovrebbe essere sempre una configurazione esterna come già segnato in altri punti
        $userAuthorized = $this->headerAuthorization->getUser();

        $redazioneUsers = array(1);
        if (in_array($userAuthorized, $redazioneUsers)) {
            return true;
        }

        return false;
    }

}

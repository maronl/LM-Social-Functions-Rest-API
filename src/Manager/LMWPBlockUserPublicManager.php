<?php
/**
 * Created by PhpStorm.
 * User: maronl
 * Date: 11/10/17
 * Time: 10:58
 */

namespace LM\WPPostLikeRestApi\Manager;


use LM\WPPostLikeRestApi\Service\LMBlockUserService;
use LM\WPPostLikeRestApi\Service\LMFollowerService;

class LMWPBlockUserPublicManager
{

    /**
     * @var LMBlockUserService
     */
    private $blockUserService;
    private $version;
    private $namespace;
    private $plugin_slug;
    /**
     * @var LMFollowerService
     */
    private $followerService;

    /**
     * LMWPLikePostPublicManager constructor.
     * @param $plugin_slug
     * @param $version
     * @param LMBlockUserService $blockUserService
     */
    public function __construct(
        $plugin_slug,
        $version,
        LMBlockUserService $blockUserService,
        LMFollowerService $followerService
    ) {
        $this->plugin_slug = $plugin_slug;
        $this->version = $version;
        $this->namespace = $this->plugin_slug . '/v' . $this->version;
        $this->blockUserService = $blockUserService;
        $this->followerService = $followerService;
    }

    /**
     * Add the endpoints to the API
     */
    public function add_api_routes()
    {
        register_rest_route($this->namespace, 'users/block', [
            'methods' => 'POST',
            'callback' => array($this, 'blockUser'),
        ]);

        register_rest_route($this->namespace, 'users/unblock', array(
            'methods' => 'POST',
            'callback' => array($this, 'unblockUser'),
        ));

        register_rest_route($this->namespace, 'users/blocked', array(
            'methods' => 'GET',
            'callback' => array($this, 'getBlockedUsersList'),
        ));

        register_rest_route($this->namespace, 'users/blocked/count', array(
            'methods' => 'GET',
            'callback' => array($this, 'getBlockedUsersCount'),
        ));
    }


    public function blockUser($request)
    {
        $blockingId = $request->get_param('user_id');
        $blockedId = $request->get_param('blocked_user_id');

        if (empty($blockingId) || empty($blockedId)) {
            return array('status' => false);
        }

        $status = $this->blockUserService->blockUser($blockingId, $blockedId);

        // rimuovo eventuali relazioni di following
        $this->followerService->removeFollower($blockingId, $blockedId);
        $this->followerService->removeFollower($blockedId, $blockingId);

        do_action('lm-sf-blocked-user', $blockingId, $blockedId);

        return array('status' => $status);
    }

    public function unblockUser($request)
    {
        $blockingId = $request->get_param('user_id');
        $blockedId = $request->get_param('blocked_user_id');

        if (empty($blockingId) || empty($blockedId)) {
            return array('status' => false);
        }

        $status = $this->blockUserService->unblockUser($blockingId, $blockedId);

        do_action('lm-sf-unblocked-user', $blockingId, $blockedId);

        return array('status' => $status);
    }

    /**
     * @return array
     */
    public function getBlockedUsersList($request)
    {
        $blockingId = $request->get_param('user_id');
        $page = $request->get_param('page');
        $item_per_page = $request->get_param('item_per_page');
        $before = $request->get_param('before');
        if (!empty($before)) {
            // aggiungo un secondo così da poter conteggiare anche il post con la data indicata
            $before = \DateTime::createFromFormat('Y-m-d H:i:s', $before);
            $before->add(new \DateInterval('PT1S'));
            $before = $before->format('Y-m-d H:i:s');
        }
        if (empty($page)) {
            $page = 1;
        }

        if (empty($item_per_page)) {
            $item_per_page = 20;
        }

        if (empty($blockingId)) {
            return array('status' => false);
        }

        $blockedUsers = $this->blockUserService->getBlockedUsersList($blockingId, $page, $item_per_page, $before);

        return array(
            'status' => true,
            'data' => $blockedUsers,
            'total' => $this->blockUserService->getBlockedUsersCount($blockingId),
            'page' => $page,
            'item_per_page' => $item_per_page,
            'time_server' => date('Y-m-d H:i:s')
        );
    }

    public function getBlockedUsersCount($request)
    {
        $blockingId = $request->get_param('user_id');

        if (empty($blockingId)) {
            return array('status' => false);
        }

        $followers = $this->blockUserService->getBlockedUsersCount($blockingId);
        return array('status' => true, 'data' => $followers);
    }



}
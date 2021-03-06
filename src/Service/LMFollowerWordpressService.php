<?php
/**
 * Created by PhpStorm.
 * User: maronl
 * Date: 11/10/17
 * Time: 10:04
 */

namespace LM\WPPostLikeRestApi\Service;


use LM\WPPostLikeRestApi\Utility\LMWPFollowerChaceCounter;
use LM\WPPostLikeRestApi\Repository\LMFollowerRepository;

class LMFollowerWordpressService implements LMFollowerService
{

    use LMWPFollowerChaceCounter;

    private $table;
    /**
     * @var LMFollowerRepository
     */
    private $repository;

    private $cacheFollowerCounter;

    private $cacheFollowingCounter;

    function __construct(LMFollowerRepository $repository)
    {
        $this->table = $repository->getTableName();
        $this->repository = $repository;
        $this->cacheFollowerCounter = 'lm-follower-counter';
        $this->cacheFollowingCounter = 'lm-following-counter';
    }

    public function addFollower($followerId, $followingId)
    {

        if ($this->repository->findFollower($followerId, $followingId)) {
            return true;
        }

        if ($this->repository->saveFollower($followerId, $followingId)) {
            $this->incrementFollowerCounters($followerId, $followingId, $this->cacheFollowerCounter,
                $this->cacheFollowingCounter, $this->repository);
            return true;
        }

        return false;
    }

    public function removeFollower($followerId, $followingId)
    {
        if ($this->repository->deleteFollower($followerId, $followingId) !== false) {
            $this->decrementFollowerCounters($followerId, $followingId, $this->cacheFollowerCounter,
                $this->cacheFollowingCounter, $this->repository);
            return true;
        }
        return false;
    }

    public function checkUserFollower($followerId, $followingId)
    {
        if ($this->repository->findFollower($followerId, $followingId)) {
            return true;
        }
        return false;
    }


    public function getFollowersCount($followingId)
    {
        $count = get_user_meta($followingId, $this->cacheFollowerCounter, true);

        if (!is_numeric($count)) {
            return 0;
        }

        return $count;
    }

    public function getFollowingsCount($followerId)
    {
        $count = get_user_meta($followerId, $this->cacheFollowingCounter, true);

        if (!is_numeric($count)) {
            return 0;
        }

        return $count;
    }

    public function getFollowers($followingId, $page, $item_per_page, $before = null)
    {
        return $this->repository->findFollowers($followingId, $page, $item_per_page, $before);
    }

    public function getFollowings($followerId, $page, $item_per_page, $before = null)
    {
        return $this->repository->findFollowings($followerId, $page, $item_per_page, $before);
    }

    public function getFollowingsIds($followerId)
    {
        return $this->repository->findFollowingsIds($followerId);
    }
}

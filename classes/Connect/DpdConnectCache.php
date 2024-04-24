<?php

namespace DpdConnect\classes\Connect;

class DpdConnectCache implements \DpdConnect\Sdk\Resources\CacheInterface
{
    public function setCache($key, $data, $expire)
    {
        $cache = \Cache::getInstance();
        $cache->store($key, $data);
    }

    public function getCache($key)
    {
        $cache = \Cache::getInstance();
        return $cache->get($key);
    }
}

<?php
namespace ZBlogPHP\AMP\Cache;
use ZBlogPHP\AMP\Cache\Drivers\File;

class Cache
{
    public $cache;

    public function __construct()
    {
        $this->cache = new File();
    }

    public function get ($cate, $param) {
        $key = $this->key($cate, $param);
        $item = json_decode($this->cache->get($key));
        if (is_null($item)) return null;
        if ($cate === 'list') {
            $timeObject = $this->get('global', array('action' => 'list-update-time'));
            if (!is_null($timeObject) && $item->time < $timeObject->time) {
                $this->cache->remove($key);
                return null;
            }
        }
        return $item;
    }

    public function set($cate, $param, $value) {
        $key = $this->key($cate, $param);
        $data = json_encode(array(
           'content' => $value,
           'time' => time()
        ));
        $this->cache->set($key, $data);
    }

    public function remove($cate, $param) {
        $key = $this->key($cate, $param);
        $this->cache->remove($key);
    }

    public function updateListTime() {
        $this->set('global', array('action' => 'list-update-time'), time());
    }

    private function key ($cate, $param) {
        $paramName = sha1(json_encode($param));
        $key = $cate . '-' . $paramName;
        return $key;
    }
}
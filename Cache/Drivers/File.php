<?php
namespace ZBlogPHP\AMP\Cache\Drivers;
class File implements CacheDriver
{

    private $dir = '';
    private $token = '';

    function __construct($cacheDir = '')
    {
        global $zbp;
        $this->dir = $cacheDir === '' ? $zbp->usersdir . '/cache/amp/' : $cacheDir;
        $this->token = $zbp->guid;
        if (!is_dir($this->dir)) {
            mkdir($this->dir);
        }
    }

    function key($oldKey)
    {
        return $this->dir . $oldKey . '.php';
    }

    function get($key)
    {
        $filePath = $this->key($key);
        if (!is_file($filePath)) return null;
        $text = file_get_contents($filePath);
        $items = explode("\n" . '__ZBLOGPHP_AMP_BOUNDARY__' . $key . '__' . "\n", $text);
        if (count($items) <= 2) return null;
        if (hash_hmac('sha256', $items[1], $this->token) !== trim($items[2])) {
            return null;
        }
        return base64_decode($items[1]);
    }

    function set($key, $value)
    {
        $filePath = $this->key($key);
        $boundary = "\n" . '__ZBLOGPHP_AMP_BOUNDARY__' . $key . '__' . "\n";
        $value = base64_encode($value);
        /** @noinspection PhpLanguageLevelInspection */
        file_put_contents($filePath, implode($boundary, [
            '<' . '?php ' . ' exit();' . "\n",
            $value,
            hash_hmac('sha256', $value, $this->token)
        ]));
    }

    function remove($key)
    {
        $filePath = $this->key($key);
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }

    function exists($key)
    {
        $filePath = $this->key($key);
        return file_exists($filePath);
    }

    function gc()
    {
        // @TODO: implement
    }
}
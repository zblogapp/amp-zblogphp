<?php
namespace ZBlogPHP\AMP\Cache\Drivers;
interface CacheDriver
{
    function get ($key);
    function set ($key, $value);
    function remove ($key);
    function exists ($key);
    function gc ();
}

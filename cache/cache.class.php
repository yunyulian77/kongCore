<?php

define('CACHE_DIR_NUM', 500); // 缓存目录数量，根据预期缓存文件数调整，开根号即可

/**
 * php 文件缓存
 */
class Cache {

    public $_cache_dir = "./cache";

    /**
     * 设置缓存
     * @param type $key
     * @param type $val
     * @param type $ttl  0  表示永不过期
     */
    function set($key, $val, $ttl = 0) {

        $cache_file = $this->_get_cache_path($key);
        $cache_data = "<?php";
        $cache_data .= $this->_get_expire_condition($ttl);
        $cache_data .= "\r\nreturn " . var_export($val, true);
        $cache_data .= "\r\n?>";

        file_put_contents($cache_file, $cache_data, LOCK_EX);
    }

    /**
     * 取出缓存
     * @param type $key
     */
    function get($key) {
        $cache_file = $this->_get_cache_path($key);
        if (!is_file($cache_file)) {
            return false;
        }
        return include($cache_file);
    }

    /**
     * 清空所有缓存,递归删除所有缓存目录
     */
    function clear() {
        $handler = dir($this->_cache_dir);
        while ($file = $handler->read()) {
            if ($file == '..' || $file == '.') {
                continue;
            }
            $filePath = rtrim($this->_cache_dir, '/') . "/" . $file;
            if (is_dir($filePath)) {
                to_rmdir($filePath);
            } else {
                unlink($filePath);
            }
        }
    }

    /**
     * 删除缓存文件
     * @param type $key
     */
    function delete($key) {
        echo $key . "<br/>";
        $cache_file = $this->_get_cache_path($key);
        return @unlink($cache_file);
    }

    function set_cache_dir($dir) {
        $this->_cache_dir = $dir;
    }

    /**
     * 设置过期时间
     * @param type $ttl
     * @return string
     */
    function _get_expire_condition($ttl) {
        if (!$ttl) {
            return '';
        }
        $ttl = intval($ttl);
        return "\r\n" . "if(filemtime(__FILE__)+ $ttl < time() ){return false;}" . " \r\n";
    }

    /**
     * 返回key值生成的缓存文件
     * @param type $key
     * @return type
     */
    function _get_cache_path($key) {
        $dir = str_pad(abs(crc32($key)) % CACHE_DIR_NUM, 4, '0', STR_PAD_LEFT);
        to_mkdir($this->_cache_dir . '/' . $dir);
        return $this->_cache_dir . '/' . $dir . '/' . $this->_get_file_name($key);
    }

    function _get_file_name($key) {
        return md5($key) . '.cache.php';
    }

}

class MemcacheCache {

    var $_memcache;

    function __construct($options = array('host'=>'localhost','port'=>'11211')) {
        $this->connect($options);
    }

    function connect($options) {
        $this->_memcache = new Memcache();
        $this->_memcache->connect($options['host'],$options['port']); 
    }
    
    function set($key,$val,$ttl = 0){
        return $this->_memcache->set($key,$val,$ttl);
    }
    
    function get($key){
        return $this->_memcache->get($key);
    }
    
    function clear(){
        return $this->flush();
    }
    
    function delete($key){
        return $this->delete($key);
    }

}

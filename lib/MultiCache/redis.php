<?php
/*
 * Redis Extension with:
 * http://pecl.php.net/package/redis
 */
namespace MultiCache;

class Cache_redis extends CacheBase implements CacheInterface {

	var $instant;
	var $checked_redis = FALSE;

	function __construct($config = array()) {
		if($this->checkdriver()) {
			$this->instant = new Redis();
			$this->setup($config);
			if($this->connectServer()) {
				return;
			}
			unset($this->instant);
		}
		throw new \Exception('no redis storage');
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		return class_exists('Redis');
	}

	function connectServer() {
		if(!$this->checked_redis) {
			$settings = isset($this->option['redis']) ? $this->option['redis'] : array();
			$server = array_merge(array(
				'host' => '127.0.0.1',
				'port'  => 6379,
				'password' => '',
				'database' => '',
				'timeout' => 1,
				), $settings);

			$host = $server['host'];

			$port = isset($server['port']) ? (int)$server['port'] : '';
			if($port!='') {
				$c['port'] = $port;
			}

			$password = isset($server['password']) ? $server['password'] : '';
			if($password!='') {
				$c['password'] = $password;
			}

			$database = isset($server['database']) ? $server['database'] : '';
			if($database!='') {
				$c['database'] = $database;
			}

			$timeout = isset($server['timeout']) ? $server['timeout'] : '';
			if($timeout!='') {
				$c['timeout'] = $timeout;
			}

			$read_write_timeout = isset($server['read_write_timeout']) ? $server['read_write_timeout'] : '';
			if($read_write_timeout!='') {
				$c['read_write_timeout'] = $read_write_timeout;
			}

			if(!$this->instant->connect($host,(int)$port,(int)$timeout)) {
				$this->checked_redis = TRUE;
				return FALSE;
			} else {
				if($database!='') {
					$this->instant->select((int)$database);
				}
				$this->checked_redis = TRUE;
				return TRUE;
			}
		}
		return TRUE;
	}

	function driver_set($keyword, $value = '', $time = 300, $option = array() ) {
		$value = $this->encode($value);
		if (empty($option['skipExisting'])) {
			$ret = $this->instant->set($keyword, $value, $time);
		} else {
			$ret = $this->instant->set($keyword, $value, array('xx', 'ex' => $time));
		}
		if($ret) {
			$this->index[$keyword] = 1;
		}
		return $ret;
	}

	// return cached value or null
	function driver_get($keyword, $option = array()) {
		$x = $this->instant->get($keyword);
		if($x) {
			return $this->decode($x);
		} else {
			return NULL;
		}
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		$this->instant->delete($keyword);
		unset($this->index[$keyword]);
		return TRUE;
	}

	function driver_stats($option = array()) {
		return array(
			'info' => '',
			'size' => count($this->index),
			'data' => $this->instant->info(),
		);
	}

	function driver_clean($option = array()) {
		$this->instant->flushDB();
		$this->index = array();
	}

	function driver_isExisting($keyword) {
		return ($this->instant->exists($keyword) != NULL);
	}

}

?>

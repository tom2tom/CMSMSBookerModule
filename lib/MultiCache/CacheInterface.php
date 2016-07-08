<?php
namespace MultiCache;

interface CacheInterface {

	 function __construct($config = []);
//	 function __destruct();

	/*
	 * Check whether this cache driver can be used
	 */
	 function use_driver();

	/*
	 * Set
	 * Upsert an item in cache
	 */
	 function _newsert($keyword, $value, $lifetime = FALSE);
	 function _upsert($keyword, $value, $lifetime = FALSE);

	/*
	 * Get
	 * Return cached value or NULL
	 */
	 function _get($keyword);
	 function _getall();

	/*
	 * Has
	 * Check whether an item is cached
	 */
	 function _has($keyword);

	/*
	 * Delete
	 * Delete a cached value
	 */
	 function _delete($keyword);

	/*
	 * Clean
	 * Clean up whole cache
	 */
	 function _clean();
}
?>

<?php

/**
 * The SQL-based NoSQL library for PHP -- it's NoSQL, even though it's not!
 *
 * Uses some simplistic middleware logic to create a persistent key/value store from a PDO connection.
 * 
 * Keys can be a string with anything in it, and values can be of any type, as they are converted to and from
 * json on the fly. To take advantage of some minor performance-enhancing logic, keys should be stated like this:
 * 		foo.bar.baz
 * 
 * The store has some limitations. For one, saving an object as users.admins.Heavenquake will mean that in order
 * to retrieve the admin user called Heavenquake, you will have to enter that exact key into ->get(). If you would
 * rather fetch a list of admins, you need to instead have a key called users.admins and then fill that with a PHP
 * array of admin users. Think of this as less of a database solution and more as a .json file with increased
 * performance and reliability in high concurrency deployments.
 *
 * @author	Per Sikker Hansen <persikkerhansen@gmail.com>
 * @license	Creative Commons Attribution License v.3.0. http://creativecommons.org/licenses/by/3.0/
 */
class NotNoSQL {
	
	private $db;
	private $collections = array();
	private $jsonDecodePolicy;

	const ROOT_COLLECTION = 'notnosql_root_collection';
	const JSON_DECODE_POLICY_ARRAY = 1;
	const JSON_DECODE_POLICY_OBJECT = 2;
	
	/**
	 * Create the store
	 *
	 * @param	PDO	the database object the class will write to.
	 */
	public function __construct(PDO $db) {
		$this->db = $db;
		$this->setJsonDecodePolicy(self::JSON_DECODE_POLICY_ARRAY);
	}	
	
	/**
	 * Get a value
	 *
	 * @param	string
	 * @return	mixed	Returns null if no such key.
	 */
	public function get($key) {
		// We'll take the first segment of the key and use it as the collection name (ie ~users~.admins.foo.bar etc.)
		$segments = explode('.', $key);
		if (count($segments) === 1) {
			// If there isn't a first segment, we'll put it in the hidden root collection
			return $this->statement('get', self::ROOT_COLLECTION, $key);
		} else {
			// If there is, we'll use the rest of the given key as the new key.
			$collection = array_shift($segments);
			return $this->statement('get', $collection, implode('.', $segments));
		}
	}
	
	/**
	 * Save a value
	 *
	 * @param	string
	 * @param	mixed	Anything goes. Will get json_encoded anyway.
	 * @return	bool
	 */
	public function put($key, $value) {
		// We'll take the first segment of the key and use it as the collection name (ie ~users~.admins.foo.bar.baz etc.)
		$segments = explode('.', $key);
		if (count($segments) === 1) {
			// If there isn't a first segment, we'll put it in the hidden root collection
			return $this->statement('put', self::ROOT_COLLECTION, $key, $value);
		} else {
			// If there is, we'll use the rest of the given key as the new key.
			$collection = array_shift($segments);
			return $this->statement('put', $collection, implode('.', $segments), $value);
		}
	}

	/**
	 * Change the policy for decoding JSON objects. 
	 *
	 * By default get() calls will, for performance reasons, return JSON objects as associative arrays, but your code 
	 * may require PHP object returns, in which case this method is offered.
	 *
	 * @param	int	NotNoSQL::JSON_DECODE_POLICY_ARRAY or NotNoSQL::JSON_DECODE_POLICY_OBJECT
	 * @return	void
	 */
	public function setJsonDecodePolicy($jsonDecodePolicy) {
		if ($jsonDecodePolicy !== self::JSON_DECODE_POLICY_ARRAY && $jsonDecodePolicy !== self::JSON_DECODE_POLICY_OBJECT) {
			throw new NotNoSQLException('Invalid jsonDecodePolicy value');
		}
		$this->jsonDecodePolicy = $jsonDecodePolicy;
	}

	/**
	 * Check what policy is currently used for converting JSON objects.
	 *
	 * @return	int	NotNoSQL::JSON_DECODE_POLICY_ARRAY or NotNoSQL::JSON_DECODE_POLICY_OBJECT
	 */
	public function getJsonDecodePolicy() {
		return $this->jsonDecodePolicy;
	}

	// -------------------------------------------------------------------------------------------------------------------
	
	/** 
	 * Helper method for invoking the correct prepared statement, as well as creating new collections
	 *
	 * @param	string	'get' or 'put' -- it automatically checks if it's a insert or update statement
	 * @param	string
	 * @param	string
	 * @param	string	Optional. Only applicable for put statements
	 * @return	mixed	The decoded value on get statements and true/false on put statements
	 */
	private function statement($statement, $table, $key, $value = null) {
		// Make sure the collection exists
		$this->createStatement($table);
		
		switch ($statement) {
			case 'get':
				// In a get scenario, no such key is a perfectly valid response, and will be turned into null.
				try {
					// The result is a valid PHP variable, decoded from json. 
					$result = $this->selectStatement($table, $key);
				} catch (NotNoSQLException $e) {
					$result = null;
				}
				return $result;
				break;
				
			case 'put':
				// While the put method doesn't care whether the key exists originally or not, sadly so does most databases.
				try {
					$this->foo = 'set';
					$this->selectStatement($table, $key);
					return $this->updateStatement($table, $key, $value);
				} catch (NotNoSQLException $e) {
					return $this->insertStatement($table, $key, $value);
				}
				break;
		}
	}
	
	/**
	 * Creates and remembers a table
	 *
	 * @param	string
	 * @return	void
	 */
	private function createStatement($table) {
		if (isset($this->collections[$table])) {
			// No need to spam the CREATE statements if we've already tried
			return;
		}
		$result = $this->db->query(
			'CREATE TABLE IF NOT EXISTS `' . $table . '` (`key` TEXT, `value` TEXT)'
		);
		// Either it didn't exist and we created it, or it already existed. Either way, remember that we're done here.
		$this->collections[$table] = true;
	}
	
	/**
	 * Selects data from a collection
	 *
	 * @param	string
	 * @param	string
	 * @return	mixed	Decoded json value
	 */
	private function selectStatement($table, $key) {
		$result = $this->db->query(
			'SELECT `value` FROM `' . $table . '` WHERE `key` = "' . $this->escape($key) . '"'
		)->fetchColumn();

		if ($result === false) {
			// No such key can mean different things, so instead of returning null we let the recipient deal with it.
			throw new NotNoSQLException('NO SUCH KEY');
		} else {
			// The value is decoded using the policy decided on with setJsonDecodePolicy. Default is associative array.
			return json_decode($this->unEscape($result), (
				$this->getJsonDecodePolicy() === self::JSON_DECODE_POLICY_ARRAY ?
					true :
					false
			));
		}
	}
	
	/**
	 * Inserts a new row in a collection
	 *
	 * @param	string
	 * @param	string
	 * @param	mixed	Anything goes. Will get transformed into JSON on the fly.
	 * @return	void
	 */
	private function insertStatement($table, $key, $value) {
		$this->db->query(	
			'INSERT INTO `' . $table . '` (`key`, `value`) VALUES("' . $this->escape($key) . '", "' . $this->escape(json_encode($value)) . '")'
		);
	}
	
	/**
	 * Updates a row in a collection
	 *
	 * @param	string
	 * @param	string
	 * @param	mixed	Anything goes. Will get transformed into JSON on the fly.
	 * @return	void
	 */
	private function updateStatement($table, $key, $value) {
		$this->db->query(
			'UPDATE `' . $table . '` SET `value` = "' . $this->escape(json_encode($value)) . '" WHERE `key` = "' . $this->escape($key) . '"'
		);
	}

	/**
	 * Helper method for sanitizing input
	 *
	 * @param	string
	 * @return	void
	 */
	private function escape($value) {
		return base64_encode($value);
	}

	/**
	 * Helper method for making output human readable
	 *
	 * @param	string
	 * @return	void
	 */
	private function unEscape($value) {
		return base64_decode($value);
	}
	
}

class NotNoSQLException extends Exception { }

// EOF

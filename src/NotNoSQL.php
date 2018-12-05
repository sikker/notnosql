<?php

namespace Sikker\NotNoSQL;
use Dflydev\DotAccessData\Data;

/**
 * The SQL-based NoSQL library for PHP -- it's NoSQL, even though it's not!
 *
 * Uses some simplistic middleware logic to create a persistent key/value store from a PDO connection.
 * 
 * Keys can be a string with anything in it, and values can be of any type, as they are converted to and from
 * json on the fly. To take advantage of some minor performance-enhancing logic, keys should be stated like this:
 * 		foo.bar.baz
 * 
 * The store has some limitations, and while it's not slow, is not optimized for performance or scaling. It is
 * intended for projects of smaller size that still have more concurrency than a flat .json file would reliably support.
 *
 * @package Sikker\NotNoSQL
 * @since	1.0.0
 * @author	Per Sikker Hansen <persikkerhansen@gmail.com>
 * @license	Creative Commons Attribution License v.4.0. http://creativecommons.org/licenses/by/4.0/
 */
class NotNoSQL {
	
	private $db;
	private $data = [];
	
	/**
	 * Create the store
	 *
	 * @param	\PDO the database object the class will write to
	 * @param	string OPTIONAL. Name of the table that will be created to store the data. Defaults to "notnosql_data" 
	 */
	public function __construct(\PDO $pdo, string $collectionTable = 'notnosql_data') {
		$this->db = new NotNoDb($pdo, $collectionTable);
	}	
	
	/**
	 * Get data from the NotNoSQL store
	 * 
	 * Examples:
	 * <code>
	 * $result = $notNoSQL->get("articles.localNews.missingCat"); // get this specific item
	 * $result = $notNoSQL->get("articles.localNews.newClinic"); // get this other specific item
	 * $result = $notNoSQL->get("articles.localNews"); // get both the above items and any others at his level
	 * </code>
	 * 
	 * @param	string	dot notation path to the item(s) you want to get
	 * @return	mixed	the content stored (or null if no such content)
	 */
	public function get(string $key) {
		$data = $this->getData($key);
		if ($data instanceof Data) {
			$data = $data->get($key);
		}
		return $data;
	}

	/**
	 * Insert or replace an item into the store
	 * 
	 * Examples:
	 * <code>
	 * $notNoSQL->put("articles.localNews.missingCat", ["title" => "Missing act", "content" => "Have you seen Mr. Smith's cat?"]);
	 * $notNoSQL->put("articles.localNews.missingCat.title", "Missing cat"); // Replacing the typoed title from before
	 * </code>
	 * 
	 * @param	string
	 * @param	mixed
	 * @return	void
	 */
	public function put(string $key, $value) {
		$segments = explode('.', $key);
		$toplevel = $segments[0];
		if (count($segments) === 1) {
			$data = $this->getData($key);
		} else {
			$data = $this->prepareData($key, $value);
		}
		if($data instanceof Data) {
			$data->set($key, $value);
			$data = $data->export();
		}
		$this->db->statement('put', $toplevel, $data);
	}

	/**
	 * Adds an item to an array in the store
	 * 
	 * Example:
	 * <code>
	 * $notNoSQL->add("users", ["username" => "sikker", password="veeeeery secret"]);
	 * </code>
	 * 
	 * @param	string
	 * @param	mixed
	 * @return	void
	 * @throws	NotAnArrayException
	 */
	public function add(string $key, $value) {
		$segments = explode('.', $key);
		$toplevel = $segments[0];
		$data = $this->prepareData($key, $value);
		if($data instanceof Data) {
			$data->append($key, $value);
			$data = $data->export();
		} else {
			throw new NotAnArrayException($key . ' is not an array, use the put method to make it an array first.');
		}
		$this->db->statement('put', $toplevel, $data);
	}

	/**
	 * Deletes an item from the store
	 * 
	 * Examples:
	 * <code>
	 * $notNoSQL->delete("articles.localNews.missingCat"); // deletes this specific item and its contents
	 * $notNoSQL->delete("articles.localNews"); // deletes this specific item and its contents
	 * </code>
	 * 
	 * TIP! if you just want to "empty out" an array of its contents, you will want to use the put method to 
	 * replace it with an empty array instead of deleting it, like so:
	 * <code>
	 * $notNoSQL->put("articles.localNews", []);
	 * </code>
	 * 
	 * @param	string
	 * @return	void
	 */
	public function delete(string $key) {
		$segments = explode('.', $key);
		$toplevel = $segments[0];
		$data = $this->prepareData($key, $value);
		if($data instanceof Data) {
			$data->remove($key);
			$data = $data->export();
		} else {
			$data = null;
		}
		$this->db->statement('put', $toplevel, $data);
	}

	/**
	 * Get raw data from the store
	 *
	 * @param	string
	 * @return	Data|mixed	Returns null if no such key.
	 */
	private function getData($key) {
		$toplevel = explode('.', $key)[0];
		$result = $this->db->statement('get', $toplevel);
		if ($result) {
			$result = json_decode($result, true);
			if (!is_array($result)) {
				return $result;
			} else {
				$data = new Data($result);
				if (!$data->has($key)) {
					return null;
				}
				return $data;
			}
		} else {
			return null;
		}
	}

	/**
	 * Prepare data for insertion
	 *
	 * @param	string
	 * @param	mixed	Anything goes. Will get json_encoded anyway.
	 * @return	Data|mixed
	 */
	private function prepareData($key, $value) {
		$segments = explode('.', $key);
		$toplevel = $segments[0];
		$currentValue = $this->getData($key);
		if (count($segments) === 1) { 
			return new $currentValue;
		} else {
			if ($currentValue) {
				$data = new Data($currentValue->export());
			} else {
				$data = new Data();
			}
			return $data;
		}
	}
	
}
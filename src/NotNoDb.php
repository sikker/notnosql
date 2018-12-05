<?php

namespace Sikker\NotNoSQL;

/**
 * The database layer for the NotNoSQL store
 *
 * Provides the nitty gritty database interaction for the NotNoSQL class. Takes a PDO object to let you decide where
 * to connect to, and optionally lets you configure the database table that will be used for data.
 *
 * @package Sikker\NotNoSQL
 * @since   2.0.0
 * @author  Per Sikker Hansen <persikkerhansen@gmail.com>
 * @license Creative Commons Attribution License v.4.0. http://creativecommons.org/licenses/by/4.0/
 */
class NotNoDb
{

    private $pdo;
    private $collectionTable;
    private $preparedStatements = [];
    private $collections = array();

    /**
     * Create the database layer for injection into the NotNoSQL store
     *
     * @param \PDO    The database connection to use.
     * @param string    OPTIONAL. Name of the table that will be created to store the data. Defaults to "notnosql_data"
     */
    public function __construct(\PDO $pdo, string $collectionTable = 'notnosql_data')
    {
        $this->collectionTable = $collectionTable;
        $this->pdo = $pdo;
        $this->createStatement();
    }

    /**
     * Entry method for interfacing with the store
     *
     * @param  string    'get' or 'put' -- it automatically checks if it's a insert or update statement
     * @param  string
     * @return mixed    The decoded value on get statements and true/false on put statements
     */
    public function statement(string $statement, string $key, $value = null)
    {
        // Make sure the collection exists
        switch ($statement) {
            case 'get':
                // In a get scenario, no such key is a perfectly valid response, and will be turned into null.
                try {
                     // The result is a valid PHP variable, decoded from json.
                     $result = $this->selectStatement($key);
                } catch (NoSuchKeyException $e) {
                     $result = null;
                }
                return $result;
            break;
            case 'put':
                // The put method doesn't care whether the key exists originally or not, but sadly most databases do.
                try {
                     $this->selectStatement($key);
                     return $this->updateStatement($key, $value);
                } catch (NoSuchKeyException $e) {
                     return $this->insertStatement($key, $value);
                }
                break;
        }
    }
    
    /**
     * Creates and remembers a table
     *
     * @param  string
     * @return bool    Success
     */
    private function createStatement()
    {
        if (!isset($this->preparedStatements['create'])) {
            $this->preparedStatements['create'] = $this->pdo->prepare(
                'CREATE TABLE IF NOT EXISTS `' . $this->collectionTable . '` (`key` TEXT, `value` TEXT)'
            );
        }

        return $result = $this->preparedStatements['create']->execute();
    }
    
    /**
     * Selects data from a collection
     *
     * @param  string
     * @return mixed    Decoded json value
     */
    private function selectStatement(string $key)
    {
        if (!isset($this->preparedStatements['select'])) {
            $this->preparedStatements['select'] = $this->pdo->prepare(
                'SELECT `value` FROM `' . $this->collectionTable . '` WHERE `key` = ?'
            );
        }

        $this->preparedStatements['select']->execute([$key]);
        $result = $this->preparedStatements['select']->fetchColumn();
        if ($result === false) {
            // No such key can mean different things, so instead of returning null we let the recipient deal with it.
            throw new NoSuchKeyException('No such key ' . $key);
        } else {
            return $result;
        }
    }
    
    /**
     * Inserts a new row in a collection
     *
     * @param  string
     * @param  mixed    Anything goes. Will get transformed into JSON on the fly.
     * @return bool    Success
     */
    private function insertStatement(string $key, $value)
    {
        if (!isset($this->preparedStatements['insert'])) {
            $this->preparedStatements['insert'] = $this->pdo->prepare(
                'INSERT INTO `' . $this->collectionTable . '` (`key`, `value`) VALUES(?, ?)'
            );
        }

        return $this->preparedStatements['insert']->execute([$key, json_encode($value)]);
    }
    
    /**
     * Updates a row in a collection
     *
     * @param  string
     * @param  mixed    Anything goes. Will get transformed into JSON on the fly.
     * @return bool    Success
     */
    private function updateStatement(string $key, $value)
    {
        if (!isset($this->preparedStatements['update'])) {
            $this->preparedStatements['update'] = $this->pdo->prepare(
                'UPDATE `' . $this->collectionTable . '` SET `value` = ? WHERE `key` = ?'
            );
        }
        
        return $this->preparedStatements['update']->execute([json_encode($value), $key]);
    }
}

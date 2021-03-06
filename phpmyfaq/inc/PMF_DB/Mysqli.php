<?php
/**
 * The PMF_DB_Mysqli class provides methods and functions for a MySQL 4.1.x,
 * 5.0.x, 5.1.x, and 6.x databases.
 *
 * @package    phpMyFAQ
 * @subpackage PMF_DB
 * @author     Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author     David Soria Parra <dsoria@gmx.net>
 * @package    2005-02-21
 * @copyright  2005-2009 phpMyFAQ Team
 * @version    SVN: $Id$
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 */

/**
 * PMF_DB_Mysqli
 *
 * @package    phpMyFAQ
 * @subpackage PMF_DB
 * @author     Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author     David Soria Parra <dsoria@gmx.net>
 * @package    2005-02-21
 * @copyright  2005-2009 phpMyFAQ Team
 * @version    SVN: $Id$
 */
class PMF_DB_Mysqli implements PMF_DB_Driver
{
    /**
     * The connection object
     *
     * @var   mixed
     * @see   connect(), query(), dbclose()
     */
    private $conn = false;

    /**
     * The query log string
     *
     * @var   string
     * @see   query()
     */
    private $sqllog = '';

    /**
     * Tables
     *
     * @var     array
     */
    public $tableNames = array();

    /**
     * Connects to the database.
     *
     * This function connects to a MySQL database
     *
     * @param   string $host
     * @param   string $username
     * @param   string $password
     * @param   string $db_name
     * @return  boolean true, if connected, otherwise false
     */
    public function connect($host, $user, $passwd, $db)
    {
        $this->conn = new mysqli($host, $user, $passwd, $db);
        if (mysqli_connect_errno()) {
            PMF_Db::errorPage(mysqli_connect_error());
            die();
        }
        
        /* change character set to UTF-8 */
        if (!$this->conn->set_charset('utf8')) {
        	PMF_Db::errorPage($this->error());
        }
        
        return true;
    }



    /**
     * Sends a query to the database.
     *
     * This function sends a query to the database.
     *
     * @param   string $query
     * @return  mixed $result
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-02-21
     */
    public function query($query)
    {
        $this->sqllog .= pmf_debug($query);
        return $this->conn->query($query);
    }



    /**
    * Escapes a string for use in a query
    *
    * @param   string
    * @return  string
    * @access  public
    * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
    * @since   2004-12-16
    */
    public function escapeString($string)
    {
      return $this->conn->real_escape_string($string);
    }



    /**
     * Fetch a result row as an object
     *
     * This function fetches a result row as an object.
     *
     * @param   mixed $result
     * @return  mixed
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-02-21
     */
    public function fetchObject($result)
    {
        return $result->fetch_object();
    }



    /**
     * Fetch a result row as an object
     *
     * This function fetches a result as an associative array.
     *
     * @param   mixed $result
     * @return  array
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-02-21
     */
    public function fetch_assoc($result)
    {
        return $result->fetch_assoc();
    }

    /**
     * Fetches a complete result as an object
     *
     * @param  resource      $result Resultset
     * @return PMF_DB_Mysqli
     */
    public function fetchAll($result)
    {
        $ret = array();
        if (false === $result) {
            throw new Exception('Error while fetching result: ' . $this->error());
        }
        
        while ($row = $this->fetchObject($result)) {
            $ret[] = $row;
        }
        
        return $ret;
    }
    
    /**
     * Number of rows in a result
     *
     * @param   mixed $result
     * @return  integer
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-02-21
     */
    public function numRows($result)
    {
        return $result->num_rows;
    }

    /**
     * Logs the queries
     *
     * @param   mixed $result
     * @return  integer
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-02-21
     */
    public function sqllog()
    {
        return $this->sqllog;
    }



    /**
     * Generates a result based on search a search string.
     *
     * This function generates a result set based on a search string.
     * FIXME: can extend to handle operands like google
     *
     * @access  public
     * @author  Tom Rochester <tom.rochester@gmail.com>
     * @author  Matteo scaramuccia <matteo@scaramuccia.com>
     * @since   2005-02-21
     */
    public function search($table, Array $assoc, $joinedTable = '', Array $joinAssoc = array(), $match = array(), $string = '', Array $cond = array(), Array $orderBy = array())
    {
        $string = $this->escapeString(trim($string));
        $fields = "";
        $joined = "";
        $where = "";
        foreach ($assoc as $field) {

            if (empty($fields)) {

                $fields = $field;
            } else {

                $fields .= ", ".$field;
            }
        }

        if (isset($joinedTable) && $joinedTable != '') {

            $joined .= ' LEFT JOIN '.$joinedTable.' ON ';
        }

        if (is_array($joinAssoc)) {

            foreach ($joinAssoc as $joinedFields) {
                $joined .= $joinedFields.' AND ';
                }
            $joined = PMF_String::substr($joined, 0, -4);
        }

        foreach ($cond as $field => $data) {
            if (empty($where)) {
                $where .= $field." = ".$data;
            } else {
                $where .= " AND ".$field." = ".$data;
            }
        }

        $match = implode(",", $match);

        if (is_numeric($string)) {
            $query = "SELECT ".$fields." FROM ".$table.$joined." WHERE ".$match." = ".$string;
        } else {
            $query = "SELECT ".$fields." FROM ".$table.$joined." WHERE MATCH (".$match.") AGAINST ('".$string."' IN BOOLEAN MODE)";
        }

        if (!empty($where)) {
            $query .= " AND (".$where.")";
        }

        $firstOrderBy = true;
        foreach ($orderBy as $field) {
            if ($firstOrderBy) {
                $query .= " ORDER BY ".$field;
                $firstOrderBy = false;
            } else {
                $query .= ", ".$field;
            }
        }

        return $this->query($query);
    }

     /**
     * Returns the error string.
     *
     * This function returns the table status.
     *
     * @access  public
     * @author  Tom Rochester <tom.rochester@gmail.com>
     * @since   2005-02-21
     */
    public function getTableStatus()
    {
        $arr = array();
        $result = $this->query("SHOW TABLE STATUS");
        while ($row = $this->fetch_assoc($result)) {
            $arr[$row["Name"]] = $row["Rows"];
        }
        return $arr;
    }

    /**
    * Returns the next ID of a table
    *
    * This function is a replacement for MySQL's auto-increment so that
    * we don't need it anymore.
    *
    * @param   string      the name of the table
    * @param   string      the name of the ID column
    * @return  int
    * @access  public
    * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
    * @since   2005-02-21
    */
    public function nextID($table, $id)
    {
        $select = sprintf("
           SELECT
               MAX(%s) AS current_id
           FROM
               %s",
           $id,
           $table);
           
        $result  = $this->query($select);
        $current = $result->fetch_row();
        return $current[0] + 1;
    }

     /**
     * Returns the error string.
     *
     * This function returns the last error string.
     *
     * @access  public
     * @author  Tom Rochester <tom.rochester@gmail.com>
     * @since   2005-02-21
     */
    public function error()
    {
        return $this->conn->error;
    }

    /**
     * Returns the client version string.
     *
     * This function returns the version string.
     *
     * @access  public
     * @author  Tom Rochester <tom.rochester@gmail.com>
     * @since   2005-02-21
     */
    public function client_version()
    {
        return $this->conn->get_client_info();
    }

    /**
     * Returns the server version string.
     *
     * This function returns the version string.
     *
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-02-21
     */
    public function server_version()
    {
        return $this->conn->get_server_info();
    }

    /**
     * Returns an array with all table names
     *
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @author  Matteo Scaramuccia <matteo@scaramuccia.com>
     * @since   2006-08-21
     */
    function getTableNames($prefix = '')
    {
        // First, declare those tables that are referenced by others
        $this->tableNames[] = $prefix.'faquser';

        $result = $this->query('SHOW TABLES'.(('' == $prefix) ? '' : ' LIKE \''.$prefix.'%\''));
        while ($row = $this->fetchObject($result)) {
            foreach ($row as $tableName) {
                if (!in_array($tableName, $this->tableNames)) {
                    $this->tableNames[] = $tableName;
                }
            }
        }
    }


    /**
     * Closes the connection to the database.
     *
     * This function closes the connection to the database.
     *
     * @return    void
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-02-21
     */
    public function dbclose()
    {
        if (is_resource($this->conn)) {
            $this->conn->close();
        }
    }

}

<?php
/**
 * The db_db2 class provides methods and functions for IBM DB2 Version 8.2 or
 * 9.1 databases. This will only work with the PECL extension ext/ibm_db2.
 *
 * @package    phpMyFAQ
 * @subpackage PMF_DB
 * @author     Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author     Helmut Tessarek <tessus@evermeet.cx>
 * @since      2005-04-16
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

class PMF_DB_Ibm_db2 implements PMF_DB_Driver
{
    /**
     * The connection object
     *
     * @var mixed
     */
    private $conn = false;

    /**
     * The query log string
     *
     * @var string
     */
    private $sqllog = '';

    /**
     * Tables
     *
     * @var array
     */
    public $tableNames = array();

    /**
     * The options array for DB2
     *
     * @var array
     */
    private $options = array('autocommit' => DB2_AUTOCOMMIT_ON);

    /**
     * This function connects to a DB2 database
     *
     * @param   string $host
     * @param   string $username
     * @param   string $password
     * @param   string $db_name
     * @return  boolean TRUE, if connected, otherwise FALSE
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function connect($host, $user, $passwd, $db)
    {
        $this->conn = db2_pconnect($db, $user, $passwd, $this->options);
        if (false == $this->conn) {
            PMF_Db::errorPage(db2_conn_errormsg());
            die();
        }
        return true;
    }

    /**
     * This function sends a query to the database.
     *
     * @param   string $query
     * @return  mixed $result
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function query($query)
    {
        $this->sqllog .= pmf_debug($query);
        return db2_exec($this->conn, $query, array('cursor' => DB2_SCROLLABLE));
    }

    /**
     * Escapes a string for use in a query
     *
     * @param   string
     * @return  string
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function escapeString($string)
    {
      return str_replace("'", "''", $string);
    }

    /**
     * This function fetches a result row as an object.
     *
     * @param   mixed $result
     * @return  mixed
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @author  Helmut Tessarek <tessus@evermeet.cx>
     * @since   2005-04-16
     */
    function fetchObject($result)
    {
        $_result = db2_fetch_object($result);
        if (is_object($_result)) {
            return (object)array_change_key_case(get_object_vars($_result), CASE_LOWER);
        } else {
            return false;
        }
    }

    /**
     * This function fetches a result as an associative array.
     *
     * @param   mixed $result
     * @return  array
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function fetch_assoc($result)
    {
        $_result = db2_fetch_assoc($result);
        if (is_array($_result)) {
            return array_change_key_case($_result, CASE_LOWER);
        } else {
            return false;
        }
    }

    /**
     * Fetches a complete result as an object
     *
     * @param  resource       $result Resultset
     * @return PMF_DB_Ibm_db2
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
     * @since   2005-04-16
     */
    function numRows($result)
    {
        return db2_num_rows($result);
    }

    /**
     * Logs the queries
     *
     * @param   mixed $result
     * @return  integer
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function sqllog()
    {
        return $this->sqllog;
    }

    /**
     * This function returns the table status.
     *
     * @return void
     * @access public
     * @author Matteo Scaramuccia <matteo@scaramuccia.com>
     * @author Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since  2006-08-26
     */
    public function getTableStatus()
    {
        $tables = array();
        $this->getTableNames(SQLPREFIX);
        foreach ($this->tableNames as $table) {
        	$result = db2_statistics($this->conn, null, null, $table);
        	while ($res = db2_fetch_assoc($result)) {
                $tables[strtolower($table)] = $res['CARDINALITY'];
        	}
        }

        return $tables;
    }



    /**
     * This function generates a result set based on a search string.
     *
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @author  Matteo scaramuccia <matteo@scaramuccia.com>
     * @since   2005-04-16
     */
    function search($table, Array $assoc, $joinedTable = '', Array $joinAssoc = array(), $match = array(), $string = '', Array $cond = array(), Array $orderBy = array())
    {
        $string = trim($string);
        $fields = '';
        $join = '';
        $joined = '';
        $where = '';

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
                $join .= $joinedFields.' AND ';
                }
            $joined .= PMF_String::substr($join, 0, -4);
        }

        $keys = PMF_String::preg_split("/\s+/", $string);
        $numKeys = count($keys);
        $numMatch = count($match);

        for ($i = 0; $i < $numKeys; $i++) {
            if (strlen($where) != 0 ) {
                $where = $where." OR";
            }
            $where = $where." (";
            for ($j = 0; $j < $numMatch; $j++) {
                if ($j != 0) {
                    $where = $where." OR ";
                }
                $where = $where.$match[$j]." LIKE '%".$keys[$i]."%'";
            }

            $where .= ")";
        }

        foreach ($cond as $field => $data) {
            if (empty($where)) {
                $where .= $field." = ".$data;
            } else {
                $where .= " AND ".$field." = ".$data;
            }
        }

        $query = "SELECT ".$fields." FROM ".$table.$joined." WHERE";

        if (!empty($where)) {
            $query .= " (".$where.")";
        }

        if (is_numeric($string)) {
            $query = "SELECT ".$fields." FROM ".$table.$joined." WHERE ".$match." = ".$string;
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
     * Returns the next ID of a table
     *
     * @param   string      the name of the table
     * @param   string      the name of the ID column
     * @return  int
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function nextID($table, $id)
    {
        $result = $this->query('SELECT MAX('.$id.') as current_id FROM '.$table);
        $row = $this->fetchObject($result);
        return ($row->current_id + 1);
    }

    /**
     * This function returns the last error string.
     *
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function error()
    {
        return db2_stmt_errormsg($this->conn);
    }

    /**
     * This function returns the version string.
     *
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function client_version()
    {
        $client = db2_client_info($this->conn);
        $ver = $client->DRIVER_NAME.' '.$client->DRIVER_VER;
        return $ver;
    }



    /**
     * This function returns the version string.
     *
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function server_version()
    {
        $server = db2_server_info($this->conn);
        $ver = $server->DBMS_NAME.' '.$server->DBMS_VER;
        return $ver;
    }



    /**
     * Creates an array with all table names
     *
     * @return  void
     * @access  public
     * @author  Matteo Scaramuccia <matteo@scaramuccia.com>
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2006-08-26
     */
    function getTableNames($prefix = '')
    {
        $stmt = db2_tables($this->conn);

        while ($table = db2_fetch_assoc($stmt)) {
            if ($table['TABLE_TYPE'] == 'TABLE' && strstr($table['TABLE_NAME'], $prefix.'FAQ')) {
                $this->tableNames[] = $table['TABLE_NAME'];
            }
        }
    }

    /**
     * This function closes the connection to the database.
     *
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @since   2005-04-16
     */
    function dbclose()
    {
        return db2_close($this->conn);
    }
}

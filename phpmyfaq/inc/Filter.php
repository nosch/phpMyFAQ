<?php
/**
 * ext/filter wrapper class
 *
 * PHP Version 5.2
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
 * 
 * @category  phpMyFAQ
 * @package   Filter
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2009-2010 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License Version 1.1
 * @link      http://www.phpmyfaq.de
 * @since     2009-01-28
 */

/**
 * PMF_Filter
 *
 * @category  phpMyFAQ
 * @package   Filter
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2009-2010 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License Version 1.1
 * @link      http://www.phpmyfaq.de
 * @since     2009-01-28
 */
class PMF_Filter
{
    /**
     * Static wrapper method for filter_input()
     *
     * @param  integer $type          Filter type
     * @param  string  $variable_name Variable name
     * @param  integer $filter        Filter 
     * @param  mixed   $default       Default value
     * @return mixed
     */
    public static function filterInput ($type, $variable_name, $filter, $default = null)
    {
        $return = filter_input($type, $variable_name, $filter);
        return (is_null($return) || $return === false) ? $default : $return;
    }
    
    /**
     * Static wrapper method for filter_input_array()
     * 
     * @param  integer $type       Filter type
     * @param  array   $definition Definition
     * @return mixed
     */
    public static function filterInputArray ($type, Array $definition)
    {
        return filter_input_array($type, $definition);
    }
    
    /**
     * Static wrapper method for filter_var()
     * 
     * @param  mixed   $variable Variable
     * @param  integer $filter   Filter
     * @param  mixed   $default       Default value
     * @return mixed
     */
    public static function filterVar ($variable, $filter, $default = null)
    {
        $return = filter_var($variable, $filter);
        return ($return === false) ? $default : $return;
    }
    
    /**
     * Filters a query string
     *
     * @return string
     */
    public static function getFilteredQueryString ()
    {
        $queryString = '';
        $urlData     = $cleanUrlData = array();
        
        parse_str($_SERVER['QUERY_STRING'], $urlData);
        
        foreach ($urlData as $key => $urlPart) {
            $cleanUrlData[strip_tags($key)] = strip_tags($urlPart);
        }
        
        $queryString = http_build_query($cleanUrlData);
        
        return $queryString;
    }
}

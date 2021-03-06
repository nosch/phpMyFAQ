<?php
/**
 * Abstract parent for the string wrapper classes 
 *
 * @package    phpMyFAQ
 * @subpackage PMF_String
 * @license    MPL
 * @author     Anatoliy Belsky <ab@php.net>
 * @since      2009-04-06
 * @version    SVN: $Id$
 * @copyright  2009 phpMyFAQ Team
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
 * PMF_String_Abstract
 *
 * @package    phpMyFAQ
 * @subpackage PMF_String
 * @license    MPL
 * @author     Anatoliy Belsky <ab@php.net>
 * @since      2009-04-06
 * @version    SVN: $Id$
 * @copyright  2009 phpMyFAQ Team
 */
abstract class PMF_String_Abstract
{
    /**
     * Default encoding
     * 
     * @var string
     */
    const DEFAULT_ENCODING = 'utf-8';
	
    
    /**
     * Default language
     * @var string
     */
    const DEFAULT_LANGUAGE = 'en';
    
    
    /**
     * Encoding
     * 
     * @var string
     */
    protected $encoding = self::DEFAULT_ENCODING;
    
    
    /**
     * Language
     * @var string
     */
    protected $language = self::DEFAULT_LANGUAGE;
    
    
    /**
     * Check if the string is a unicode string
     * 
     * @param string $str String
     * 
     * @return boolean
     */
    public static function isUTF8($str)
    {
        $regex = '/^([\x00-\x7f]|'
               . '[\xc2-\xdf][\x80-\xbf]|'
               . '\xe0[\xa0-\xbf][\x80-\xbf]|'
               . '[\xe1-\xec][\x80-\xbf]{2}|'
               . '\xed[\x80-\x9f][\x80-\xbf]|'
               . '[\xee-\xef][\x80-\xbf]{2}|'
               . 'f0[\x90-\xbf][\x80-\xbf]{2}|'
               . '[\xf1-\xf3][\x80-\xbf]{3}|'
               . '\xf4[\x80-\x8f][\x80-\xbf]{2})*$/';

        return preg_match($regex, $str) === 1;
    }
        
    
    /**
     * Set current encoding
     * 
     * @return string
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
    }

    
    /**
     * Get current encoding
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }
}
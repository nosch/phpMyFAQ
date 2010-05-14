<?php
/**
 * The phpMyFAQ Search class
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
 * @package   PMF_Search
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author    Matteo Scaramuccia <matteo@scaramuccia.com>
 * @author    Adrianna Musiol <musiol@imageaccess.de>
 * @copyright 2008-2010 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License Version 1.1
 * @link      http://www.phpmyfaq.de
 * @since     2008-01-26
 */

/**
 * PMF_Search
 *
 * @category  phpMyFAQ
 * @package   PMF_Search
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author    Matteo Scaramuccia <matteo@scaramuccia.com>
 * @author    Adrianna Musiol <musiol@imageaccess.de>
 * @copyright 2008-2010 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License Version 1.1
 * @link      http://www.phpmyfaq.de
 * @since     2008-01-26
 */
class PMF_Search
{
    /**
     * DB handle
     *
     * @var PMF_Db
     */
    private $db;

    /**
     * Language
     *
     * @var string
     */
    private $language;

    /**
     * Category
     *
     * @var integer
     */
    private $categoryId = null;

    /**
     * Search table
     *
     * @var string
     */
    private $_table = null;

    /**
     * Category object
     *
     * @var PMF_Category
     */
    private $_category = null;

    /**
     * Language object
     *
     * @var PMF_Language
     */
    private $_lang = null;



    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->db       = PMF_Db::getInstance();
        $this->language = PMF_Language::$language;
        $this->_table   = SQLPREFIX . 'faqsearches';
        $this->_category = new PMF_Category();
        $this->_lang = new PMF_Language();
    }

    /**
     * Setter for category
     *
     * @param  integer $categoryId Category ID
     * @return void
     */
    public function setCategory($categoryId)
    {
        $this->categoryId = (int)$categoryId;
    }

    /**
     * Getter for category
     *
     * @return integer
     */
    public function getCategory()
    {
        return $this->categoryId;
    }

    /**
     * The main search function for the full text search
     *
     * @param   string  $searchterm     Text/Number (solution id)
     * @param   boolean $allLanguages   true to search over all languages
     * @param   boolean $hasMore        true to disable the results paging
     * @param   boolean $instantRespnse true to use it for Instant Response
     * @return  array
     */
    public function search($searchterm, $allLanguages = true, $hasMore = false, $instantResponse = false)
    {
        $result = null;

        $fdTable   = SQLPREFIX . 'faqdata';
        $fcrTable  = SQLPREFIX . 'faqcategoryrelations';
        $condition = array($fdTable . '.active' => "'yes'");

        // Search in all or one category?
        if (!is_null($this->categoryId)) {
            $selectedCategory = array($fcrTable . '.category_id' => $searchcategory);
            $condition        = array_merge($selectedCategory, $condition);
        }

        if ((!$allLanguages) && (!is_numeric($searchterm))) {
            $selectedLanguage = array($fdTable . '.lang' => "'" . $this->language . "'");
            $condition        = array_merge($selectedLanguage, $condition);
        }

        if (is_numeric($searchterm)) {
            // search for the solution_id
            $result = $this->db->search($fdTable,
                array(
                $fdTable . '.id AS id',
                $fdTable . '.lang AS lang',
                $fdTable . '.solution_id AS solution_id',
                $fcrTable . '.category_id AS category_id',
                $fdTable . '.thema AS question',
                $fdTable . '.content AS answer'),
                $fcrTable,
                array($fdTable . '.id = ' . $fcrTable . '.record_id',
                      $fdTable . '.lang = ' . $fcrTable . '.record_lang'),
                array($fdTable . '.solution_id'),
                $searchterm,
                $condition);
        } else {
            $result = $this->db->search($fdTable,
                array(
                $fdTable . '.id AS id',
                $fdTable . '.lang AS lang',
                $fcrTable . '.category_id AS category_id',
                $fdTable . '.thema AS question',
                $fdTable . '.content AS answer'),
                $fcrTable,
                array($fdTable . '.id = ' . $fcrTable . '.record_id',
                      $fdTable . '.lang = ' . $fcrTable . '.record_lang'),
                array($fdTable . '.thema',
                      $fdTable . '.content',
                      $fdTable . '.keywords'),
                $searchterm,
                $condition);
        }

        return $result;
    }

    /**
     * Function for rendering the search result
     *
     * TODO: add filter for (X)HTML tag names and attributes!
     *
     * @param   string  Text/Number (solution id)
     * @param   string  '%' to avoid any category filtering
     * @param   boolean true to search over all languages
     * @param   boolean true to disable the results paging
     * @param   boolean true to use it for Instant Response
     * @return  string
     * @access  public
     * @author  Thorsten Rinne <thorsten@phpmyfaq.de>
     * @author  Matteo Scaramuccia <matteo@phpmyfaq.de>
     * @author  Adrianna Musiol <musiol@imageaccess.de>
     * @since   2002-09-16
     */
    public function renderSearchResult($searchterm, $cat = '%', $allLanguages = true, $hasMore = false, $instantRespnse = false)
    {
        global $sids, $PMF_LANG, $faq, $current_user, $current_groups;

        $_searchterm = PMF_htmlentities(stripslashes($searchterm), ENT_QUOTES, 'utf-8');
        $seite       = 1;
        $output      = '';
        $num         = 0;
        $searchItems = array();
        $langs       = (true == $allLanguages) ? '&amp;langs=all' : '';
        $seite       = PMF_Filter::filterInput(INPUT_GET, 'seite', FILTER_VALIDATE_INT, 1);
        $db          = PMF_Db::getInstance();
        $faqconfig   = PMF_Configuration::getInstance();
        $plr         = new PMF_Language_Plurals($PMF_LANG);
        $LANGCODE    = $this->_lang->setLanguage($faqconfig->get('main.languageDetection'), $faqconfig->get('main.language'));

        // get result from db
        $result = $this->search($_searchterm);

        $num = $db->num_rows($result);

        if (0 == $num) {
            $output = $PMF_LANG['err_noArticles'];
        }

        $confPerPage = $faqconfig->get('main.numberOfRecordsPerPage');

        $pages = ceil($num / $confPerPage);
        $last  = $seite * $confPerPage;
        $first = $last - $confPerPage;
        if ($last > $num) {
            $last = $num;
        }

        if ($num > 0) {
            $output .= '<p>'.$plr->GetMsg('plmsgSearchAmount', $num);
            if ($hasMore && ($pages > 1)) {
                $output .= sprintf($PMF_LANG['msgInstantResponseMaxRecords'], $confPerPage);
            }
            $output .= "</p>\n";
            if (!$hasMore && ($pages > 1)) {
                $output .= "<p><strong>".$PMF_LANG["msgPage"].$seite." ".$PMF_LANG["msgVoteFrom"]." ".$plr->GetMsg('plmsgPagesTotal',$pages)."</strong></p>";
            }
            $output .= "<ul class=\"phpmyfaq_ul\">\n";

            $counter = 0;
            $displayedCounter = 0;
            while (($row = $db->fetch_object($result)) && $displayedCounter < $confPerPage) {
                $counter ++;
                if ($counter <= $first) {
                    continue;
                }
                $displayedCounter++;

                $b_permission = false;
                //Groups Permission Check
                if ($faqconfig->get('main.permLevel') == 'medium') {
                    $perm_group = $faq->getPermission('group', $row->id);
                    foreach ($current_groups as $index => $value){
                        if (in_array($value, $perm_group)) {
                            $b_permission = true;
                        }
                    }
                }
                if ($faqconfig->get('main.permLevel') == 'basic' || $b_permission) {
                    $perm_user = $faq->getPermission('user', $row->id);
                    foreach ($perm_user as $index => $value) {
                        if ($value == -1) {
                            $b_permission = true;
                            break;
                        } elseif (((int)$value == $current_user)) {
                            $b_permission = true;
                            break;
                        } else {
                            $b_permission = false;
                        }
                    }
                }

                if ($b_permission) {
                    $rubriktext  = $this->_category->getPath($row->category_id);
                    $question       = chopString($row->question, 15);
                    $answer     = chopString(strip_tags($row->answer), 25);
                    $searchterm  = str_replace(array('^', '.', '?', '*', '+', '{', '}', '(', ')', '[', ']', '"'), '', $searchterm);
                    $searchterm  = preg_quote($searchterm, '/');
                    $searchItems = explode(' ', $searchterm);

                    if (PMF_String::strlen($searchItems[0]) > 1) {
                        foreach ($searchItems as $item) {
                            if (PMF_String::strlen($item) > 2) {
                                $question = PMF_String::preg_replace_callback('/'
                                    .'('.$item.'="[^"]*")|'
                                    .'((href|src|title|alt|class|style|id|name|dir|onclick|ondblclick|onmousedown|onmouseup|onmouseover|onmousemove|onmouseout|onkeypress|onkeydown|onkeyup)="[^"]*'.$item.'[^"]*")|'
                                    .'('.$item.')'
                                    .'/mis',
                                    "highlight_no_links",
                                    $question );
                                $answer = PMF_String::preg_replace_callback('/'
                                    .'('.$item.'="[^"]*")|'
                                    .'((href|src|title|alt|class|style|id|name|dir|onclick|ondblclick|onmousedown|onmouseup|onmouseover|onmousemove|onmouseout|onkeypress|onkeydown|onkeyup)="[^"]*'.$item.'[^"]*")|'
                                    .'('.$item.')'
                                    .'/mis',
                                        "highlight_no_links",
                                    $answer);
                            }
                        }
                    }

                    // Print the link to the faq record
                    $url = sprintf(
                        '?%saction=artikel&amp;cat=%d&amp;id=%d&amp;artlang=%s&amp;highlight=%s',
                        $sids,
                        $row->category_id,
                        $row->id,
                        $row->lang,
                        urlencode($_searchterm));

                    if ($instantRespnse) {
                        $currentUrl = PMF_Link::getSystemRelativeUri('ajaxresponse.php').'index.php';
                    } else {
                        $currentUrl = PMF_Link::getSystemRelativeUri();
                    }
                    $oLink            = new PMF_Link($currentUrl.$url);
                    $oLink->itemTitle = $row->question;
                    $oLink->text      = $question;
                    $oLink->tooltip   = $row->question;
                    $output .=
                        '<li><strong>'.$rubriktext.'</strong>: '.$oLink->toHtmlAnchor().'<br />'
                        .'<div class="searchpreview"><strong>'.$PMF_LANG['msgSearchContent'].'</strong> '.$answer.'...</div>'
                        .'<br /></li>'."\n";
                }
            }
            $output .= "</ul>\n";
        } else {
            $output = $PMF_LANG["err_noArticles"];
        }

        if (!$hasMore && ($num > $confPerPage)) {
            if ($faqconfig->get('main.enableRewriteRules')) {
                $baseUrl = sprintf("search.html?search=%s&amp;seite=%d%s&amp;searchcategory=%d",
                                urlencode($_searchterm),
                                $seite,
                                $langs,
                                $cat);
            } else {
                $baseUrl = PMF_Link::getSystemRelativeUri() . '?'
                         . (empty($sids) ? '' : "$sids&amp;")
                         . 'action=search&amp;search=' . urlencode($_searchterm)
                         . '&amp;seite=' . $seite . $langs
                         . "&amp;searchcategory=" . $cat;
            }

            $options = array('baseUrl'         => $baseUrl,
                             'total'           => $num,
                             'perPage'         => $confPerPage,
                             'pageParamName'   => 'seite',
                             'nextPageLinkTpl' => '<a href="{LINK_URL}">' . $PMF_LANG["msgNext"] . '</a>',
                             'prevPageLinkTpl' => '<a href="{LINK_URL}">' . $PMF_LANG["msgPrevious"] . '</a>',
                             'layoutTpl'       => '<p align="center"><strong>{LAYOUT_CONTENT}</strong></p>');

            $pagination = new PMF_Pagination($options);
            $output    .= $pagination->render();
        }

        return $output;
    }

    /**
     * Logging of search terms for improvements
     *
     * @param  string $searchterm Search term
     * @return void
     */
    public function logSearchTerm($searchterm)
    {
        if (PMF_String::strlen($searchterm) == 0) {
            return;
        }

        $date  = new DateTime();
        $query = sprintf("
            INSERT INTO
                %s
            (id, lang, searchterm, searchdate)
                VALUES
            (%d, '%s', '%s', '%s')",
            $this->_table,
            $this->db->nextID($this->_table, 'id'),
            $this->language,
            $this->db->escape_string($searchterm),
            $date->format('Y-m-d H:i:s'));

        $this->db->query($query);
    }

    /**
     * Returns the most popular searches
     *
     * @param  integer $numResults Number of Results, default: 7
     * @param  boolean weither language must be included into result
     * @return array
     */
    public function getMostPopularSearches($numResults = 7, $withLang = false)
    {
        $searchResult = array();

        $byLang = $withLang ? ', lang' : '';
        $query  = sprintf("
            SELECT
                searchterm, COUNT(searchterm) AS number %s
            FROM
                %s
            GROUP BY
                searchterm %s
            ORDER BY
                number
            DESC",
            $byLang,
            $this->_table,
            $byLang);

        $result = $this->db->query($query);

        if ($result) {
            $i = 0;
            while ($row = $this->db->fetch_object($result)) {
                if ($i < $numResults) {
                    $searchResult[] = (array)$row;
                }
                $i++;
            }
        }

        return $searchResult;
    }

    /**
     * Returns row count from the faqsearches table
     *
     * @return integer
     */
    public function getSearchesCount()
    {
        $sql = sprintf("
            SELECT
                COUNT(1) AS count
            FROM
                %s",
        $this->_table);

        $result = $this->db->query($sql);

        return (int)$this->db->fetch_object($result)->count;
    }
}
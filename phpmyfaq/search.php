<?php
/**
 * The fulltext search page
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
 * @package   Frontend
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author    Periklis Tsirakidis <tsirakidis@phpdevel.de>
 * @license   http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License Version 1.1
 * @link      http://www.phpmyfaq.de
 * @copyright 2002-2009 phpMyFAQ Team
 * @since     2002-09-16
 */

if (!defined('IS_VALID_PHPMYFAQ')) {
    header('Location: http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']));
    exit();
}

$faqsession->userTracking('fulltext_search', 0);

// Get possible user input
$inputLanguage   = PMF_Filter::filterInput(INPUT_GET, 'langs', FILTER_SANITIZE_STRING);
$inputCategory   = PMF_Filter::filterInput(INPUT_GET, 'searchcategory', FILTER_VALIDATE_INT, '%');
$inputTag        = PMF_Filter::filterInput(INPUT_GET, 'tagging_id', FILTER_VALIDATE_INT);
$inputSearchTerm = PMF_Filter::filterInput(INPUT_GET, 'suchbegriff', FILTER_SANITIZE_STRIPPED);
$search          = PMF_Filter::filterInput(INPUT_GET, 'search', FILTER_SANITIZE_STRIPPED);

// Search only on current language (default)
$allLanguages = false;
if (!is_null($inputLanguage)) {
    $allLanguages = true;
}

// HACK: (re)evaluate the Category object w/o passing the user language
//       so the result set of a Search will have the Category Path
//       for any of the multilanguage faq records and the Category list
//       on the left pane will not be affected
if ($allLanguages) {
    $category = new PMF_Category();
    $category->transform(0);
}

$faqsearch           = new PMF_Search();
$printResult         = '';
$tagSearch           = false;
$mostPopularSearches = 'n/a'; // to be implemented

//
// Handle the Tagging ID
//
if (!is_null($inputTag)) {
    $tagSearch   = true;
    $tagging     = new PMF_Tags();
    $record_ids  = $tagging->getRecordsByTagId($inputTag);
    $printResult = $faq->showAllRecordsByIds($record_ids);
}

//
// Handle the full text search stuff
//
if (!is_null($inputSearchTerm) || !is_null($search)) {
    if (!is_null($inputSearchTerm)) {
        $inputSearchTerm = $db->escape_string(strip_tags($inputSearchTerm));
    }

    if (!is_null($search)) {
        $inputSearchTerm = $db->escape_string(strip_tags($search));
    }

    $printResult = $faqsearch->renderSearchResult($inputSearchTerm, $inputCategory, $allLanguages);

    $inputSearchTerm = stripslashes($inputSearchTerm);

    $faqsearch->logSearchTerm($inputSearchTerm);
}

// Change a little bit the $searchCategory value;
$inputCategory = ('%' == $inputCategory) ? 0 : $inputCategory;

$faqsession->userTracking('fulltext_search', $inputSearchTerm);

$category->buildTree();

$openSearchLink = sprintf('<a class="searchplugin" href="#" onclick="window.external.AddSearchProvider(\'%s/opensearch.php\');">%s</a>',
    PMF_Link::getSystemUri('/index.php'),
    $PMF_LANG['opensearch_plugin_install']);

$mostPopularSearches   = '';
$mostPopularSearchData = $faqsearch->getMostPopularSearches($faqconfig->get('main.numberSearchTerms'));

foreach ($mostPopularSearchData as $searchItem) {
    if (PMF_String::strlen($searchItem['searchterm']) > 0) {
        $mostPopularSearches .= sprintf('<li><a href="?search=%s&submit=Search&action=search">%s</a> (%dx)</li>',
            urlencode($searchItem['searchterm']),
            $searchItem['searchterm'],
            $searchItem['number']);
    }
}

$helper = PMF_Helper_Category::getInstance();
$helper->setCategory($category);

$tpl->processTemplate('writeContent', array(
    'msgSearch'                => ($tagSearch ? $PMF_LANG['msgTagSearch'] : $PMF_LANG['msgSearch']),
    'searchString'             => PMF_String::htmlspecialchars($inputSearchTerm, ENT_QUOTES, 'utf-8'),
    'searchOnAllLanguages'     => $PMF_LANG['msgSearchOnAllLanguages'],
    'checkedAllLanguages'      => $allLanguages ? ' checked="checked"' : '',
    'selectCategories'         => $PMF_LANG['msgSelectCategories'],
    'allCategories'            => $PMF_LANG['msgAllCategories'],
    'printCategoryOptions'     => $helper->renderCategoryOptions($inputCategory),
    'writeSendAdress'          => '?'.$sids.'action=search',
    'msgSearchWord'            => $PMF_LANG['msgSearchWord'],
    'printResult'              => $printResult,
    'openSearchLink'           => $openSearchLink,
    'msgMostPopularSearches'   => $PMF_LANG['msgMostPopularSearches'],
    'printMostPopularSearches' => '<ul class="phpmyfaq_ul">' . $mostPopularSearches . '</ul>'));

$tpl->includeTemplate('writeContent', 'index');

<?php
/**
 * The RSS feed with the top ten.
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
 * @package   PMF_Feed
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author    Matteo Scaramuccia <matteo@phpmyfaq.de>
 * @copyright 2004-2010 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License Version 1.1
 * @link      http://www.phpmyfaq.de
 * @since     2004-11-05
 */

define('PMF_ROOT_DIR', dirname(dirname(dirname(__FILE__))));

require_once(PMF_ROOT_DIR.'/inc/Init.php');
PMF_Init::cleanRequest();
session_name(PMF_COOKIE_NAME_AUTH . trim($faqconfig->get('main.phpMyFAQToken')));
session_start();

//
// get language (default: english)
//
$Language = new PMF_Language();
$LANGCODE = $Language->setLanguage($faqconfig->get('main.languageDetection'), $faqconfig->get('main.language'));
// Preload English strings
require_once (PMF_ROOT_DIR.'/lang/language_en.php');

if (isset($LANGCODE) && PMF_Language::isASupportedLanguage($LANGCODE)) {
    // Overwrite English strings with the ones we have in the current language
    require_once(PMF_ROOT_DIR.'/lang/language_'.$LANGCODE.'.php');
} else {
    $LANGCODE = 'en';
}

//
// Initalizing static string wrapper
//
PMF_String::init($LANGCODE);

$faq     = new PMF_Faq();
$rssData = $faq->getTopTenData(PMF_NUMBER_RECORDS_TOPTEN);
$num     = count($rssData);

$rss = new XMLWriter();
$rss->openMemory();
$rss->setIndent(true);

$rss->startDocument('1.0', 'utf-8');
$rss->startElement('rss');
$rss->writeAttribute('version', '2.0');
$rss->startElement('channel');
$rss->writeElement('title', $faqconfig->get('main.titleFAQ') . ' - ' . $PMF_LANG['msgTopTen']);
$rss->writeElement('description', html_entity_decode($faqconfig->get('main.metaDescription')));
$rss->writeElement('link', PMF_Link::getSystemUri('/feed/topten/rss.php'));

if ($num > 0) {
    $i = 0;
    foreach ($rssData as $item) {
        $i++;
        // Get the url
        $link = str_replace($_SERVER['PHP_SELF'], '/index.php', $item['url']);
        if (PMF_RSS_USE_SEO) {
            if (isset($item['thema'])) {
                $oLink = new PMF_Link($link);
                $oLink->itemTitle = $item['thema'];
                $link = $oLink->toString();
            }
        }

        $rss->startElement('item');
        $rss->writeElement('title', PMF_Utils::makeShorterText(html_entity_decode($item['thema']), 8) .
                                    " (".$item['visits']." ".$PMF_LANG['msgViews'].")");
        
        $rss->startElement('description');
        $rss->writeCdata("[".$i.".] ".$item['thema']." (".$item['visits']." ".$PMF_LANG['msgViews'].")");
        $rss->endElement();
        
        $rss->writeElement('link', PMF_Link::getSystemUri('/feed/topten/rss.php').$link);
        $rss->writeElement('pubDate', PMF_Date::createRFC822Date($item['last_visit'], false));
        $rss->endElement();
    }
}

$rss->endElement();
$rss->endElement();
$rssData = $rss->outputMemory();

header('Content-Type: application/rss+xml');
header('Content-Length: '.strlen($rssData));

print $rssData;

$db->dbclose();

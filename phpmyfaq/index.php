<?php
/**
 * This is the main public frontend page of phpMyFAQ. It detects the browser's
 * language, gets and sets all cookie, post and get informations and includes
 * the templates we need and set all internal variables to the template
 * variables. That's all.
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
 * @author    Lars Tiedemann <php@larstiedemann.de>
 * @author    Matteo Scaramuccia <matteo@phpmyfaq.de>
 * @copyright 2001-2010 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License Version 1.1
 * @link      http://www.phpmyfaq.de
 * @since     2001-02-12
 */

//
// Check if config/database.php exist -> if not, redirect to installer
//
if (!file_exists('config/database.php')) {
    header("Location: install/setup.php");
    exit();
}

//
// Define the named constant used as a check by any included PHP file
//
define('IS_VALID_PHPMYFAQ', null);

//
// Autoload classes, prepend and start the PHP session
//
require_once 'inc/Init.php';
PMF_Init::cleanRequest();
session_name(PMF_COOKIE_NAME_AUTH . trim($faqconfig->get('main.phpMyFAQToken')));
session_start();

//
// Get language (default: english)
//
$Language = new PMF_Language();
$LANGCODE = $Language->setLanguage($faqconfig->get('main.languageDetection'), $faqconfig->get('main.language'));
// Preload English strings
require_once 'lang/language_en.php';


$showCaptcha = PMF_Filter::filterInput(INPUT_GET, 'gen', FILTER_SANITIZE_STRING);
if (isset($LANGCODE) && PMF_Language::isASupportedLanguage($LANGCODE) && is_null($showCaptcha)) {
    // Overwrite English strings with the ones we have in the current language,
    // but don't include UTF-8 encoded files, these will break the captcha images
    require_once 'lang/language_'.$LANGCODE.'.php';
} else {
    $LANGCODE = 'en';
}

//Load plurals support for selected language
$plr = new PMF_Language_Plurals($PMF_LANG);

//
// Initalizing static string wrapper
//
PMF_String::init($LANGCODE);

/**
 * Initialize attachment factory
 */
PMF_Attachment_Factory::init($faqconfig->get('main.attachmentsStorageType'),
                             $faqconfig->get('main.defaultAttachmentEncKey'),
                             $faqconfig->get('main.enableAttachmentEncryption'));

//
// Get user action
//
$action = PMF_Filter::filterInput(INPUT_GET, 'action', FILTER_SANITIZE_STRING, 'main');

//
// Authenticate current user
//
$auth        = null;
$error       = '';
$faqusername = PMF_Filter::filterInput(INPUT_POST, 'faqusername', FILTER_SANITIZE_STRING);
$faqpassword = PMF_Filter::filterInput(INPUT_POST, 'faqpassword', FILTER_SANITIZE_STRING);
if (!is_null($faqusername) && !is_null($faqpassword)) {
    $user = new PMF_User_CurrentUser();
    if ($faqconfig->get('main.ldapSupport')) {
        $authLdap = new PMF_Auth_AuthLdap();
        $user->addAuth($authLdap, 'ldap');
    }
    if ($user->login($faqusername, $faqpassword)) {
        if ($user->getStatus() != 'blocked') {
            $auth = true;
        } else {
            $error = $PMF_LANG["ad_auth_fail"]." (".$faqusername." / *)";
            $user  = null;
        }
    } else {
        // error
        $error = sprintf(
            '%s<br /><a href="admin/password.php" title="%s">%s</a>',
            $PMF_LANG['ad_auth_fail'],
            $PMF_LANG['lostPassword'],
            $PMF_LANG['lostPassword']
        );
        $user = null;
    }
    $action = 'main';
} else {
    // authenticate with session information
    $user = PMF_User_CurrentUser::getFromSession($faqconfig->get('main.ipCheck'));
    if ($user) {
        $auth = true;
    } else {
        $user = null;
    }
}

//
// Get current user rights
//
$permission = array();
if (isset($auth)) {
    // read all rights, set them FALSE
    $allRights = $user->perm->getAllRightsData();
    foreach ($allRights as $right) {
        $permission[$right['name']] = false;
    }
    // check user rights, set them TRUE
    $allUserRights = $user->perm->getAllUserRights($user->getUserId());
    foreach ($allRights as $right) {
        if (in_array($right['right_id'], $allUserRights))
            $permission[$right['name']] = true;
    }
}

//
// Logout
//
if ('logout' === $action && isset($auth)) {
    $user->deleteFromSession();
    $user   = null;
    $auth   = null;
    $action = 'main';
}

//
// Get current user and group id - default: -1
//
if (!is_null($user) && $user instanceof PMF_User_CurrentUser) {
    $current_user   = $user->getUserId();
    if ($user->perm instanceof PMF_Perm_PermMedium) {
        $current_groups = $user->perm->getUserGroups($current_user);
    } else {
        $current_groups = array(-1);
    }
    if (0 == count($current_groups)) {
        $current_groups = array(-1);
    }
} else {
    $current_user   = -1;
    $current_groups = array(-1);
}

//
// Use mbstring extension if available and when possible
//
$valid_mb_strings = array('ja', 'en', 'uni');
$mbLanguage       = ($PMF_LANG['metaLanguage'] != 'ja') ? 'uni' : $PMF_LANG['metaLanguage'];
if (function_exists('mb_language') && in_array($mbLanguage, $valid_mb_strings)) {
    mb_language($mbLanguage);
    mb_internal_encoding('utf-8');
}

//
// Found a session ID in _GET or _COOKIE?
//
$sid        = null;
$sid_get    = PMF_Filter::filterInput(INPUT_GET, PMF_GET_KEY_NAME_SESSIONID, FILTER_VALIDATE_INT);
$sid_cookie = PMF_Filter::filterInput(INPUT_COOKIE, PMF_COOKIE_NAME_SESSIONID, FILTER_VALIDATE_INT);
$faqsession = new PMF_Session();
// Note: do not track internal calls
$internal = false;
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $internal = (strpos($_SERVER['HTTP_USER_AGENT'], 'phpMyFAQ%2F') === 0);
}
if (!$internal) {
    if (is_null($sid_get) && is_null($sid_cookie)) {
        // Create a per-site unique SID
        $faqsession->userTracking('new_session', 0);
    } else {
        if (!is_null($sid_cookie)) {
            $faqsession->checkSessionId($sid_cookie, $_SERVER['REMOTE_ADDR']);
        } else {
            $faqsession->checkSessionId($sid_get, $_SERVER['REMOTE_ADDR']);
        }
    }
}

//
// Is user tracking activated?
//
$sids = '';
if ($faqconfig->get('main.enableUserTracking')) {
    if (isset($sid)) {
        PMF_Session::setCookie($sid);
        if (is_null($sid_cookie)) {
            $sids = sprintf('sid=%d&amp;lang=%s&amp;', $sid, $LANGCODE);
        }
    } elseif (is_null($sid_get) || is_null($sid_cookie)) {
        if (is_null($sid_cookie)) {
            if (!is_null($sid_get)) {
                $sids = sprintf('sid=%d&amp;lang=%s&amp;', $sid_get, $LANGCODE);
            }
        }
    }
} else {
    if (!setcookie(PMF_GET_KEY_NAME_LANGUAGE, $LANGCODE, $_SERVER['REQUEST_TIME'] + PMF_LANGUAGE_EXPIRED_TIME)) {
        $sids = sprintf('lang=%s&amp;', $LANGCODE);
    }
}

//
// Found a article language?
//
$lang = PMF_Filter::filterInput(INPUT_POST, 'artlang', FILTER_SANITIZE_STRING);
if (is_null($lang) && !PMF_Language::isASupportedLanguage($lang) ) {
    $lang = $LANGCODE;
}

//
// Create a new FAQ object
//
$faq = new PMF_Faq($current_user, $current_groups);

//
// Create a new Category data provider
//
$categoryData = new PMF_Category_Tree_DataProvider_SingleQuery($LANGCODE);

//
// Create a new Tags object
//
$oTag = new PMF_Tags();

//
// Found a record ID?
//
$id = PMF_Filter::filterInput(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!is_null($id)) {
    $title           = ' - ' . $faq->getRecordTitle($id);
    $keywords        = ',' . $faq->getRecordKeywords($id);
    $metaDescription = $faq->getRecordPreview($id);
} else {
    $id              = '';
    $title           = ' -  powered by phpMyFAQ ' . $faqconfig->get('main.currentVersion');
    $keywords        = '';
    $metaDescription = $faqconfig->get('main.metaDescription');
}

//
// found a solution ID?
//
$solution_id = PMF_Filter::filterInput(INPUT_GET, 'solution_id', FILTER_VALIDATE_INT);
if (!is_null($solution_id)) {
    $title    = ' -  powered by phpMyFAQ ' . $faqconfig->get('main.currentVersion');
    $keywords = '';
    $faqData  = $faq->getIdFromSolutionId($solution_id);
    if (is_array($faqData)) {
        $id              = $faqData['id'];
        $lang            = $faqData['lang'];
        $title           = ' - ' . $faq->getRecordTitle($id);
        $keywords        = ',' . $faq->getRecordKeywords($id);
        $metaDescription = PMF_Utils::makeShorterText(strip_tags($faqData['content']), 12);
    }
} 

//
// Handle the Tagging ID
//
$tag_id = PMF_Filter::filterInput(INPUT_GET, 'tagging_id', FILTER_VALIDATE_INT);
if (!is_null($tag_id)) {
    $title    = ' - ' . $oTag->getTagNameById($tag_id);
    $keywords = '';
}

//
// Handle the SiteMap
//
$letter = PMF_Filter::filterInput(INPUT_GET, 'letter', FILTER_SANITIZE_STRIPPED);
if (!is_null($letter) && (1 == PMF_String::strlen($letter))) {
    $title    = ' - ' . $letter . '...';
    $keywords = $letter;
}

//
// Found a category ID?
//
$cat          = PMF_Filter::filterInput(INPUT_GET, 'cat', FILTER_VALIDATE_INT, 0);
$cat_from_id  = -1;
$categoryPath = array(0);
if (is_numeric($id) && $id > 0) {
    $categoryRelations = new PMF_Category_Relations();
    foreach ($categoryRelations->fetchAll() as $relation) {
        if ($relation->record_id == $id) {
            $cat_from_id = $relation->category_id;
            break;
        }
    }
}
if ($cat_from_id != -1 && $cat == 0) {
    $cat = $cat_from_id;
}

if ($cat != 0) {
    $categoryPath = $categoryData->getPath($cat);
}

/* @todo: Fix this old code
if (isset($cat) && ($cat != 0) && ($id == '') && isset($category->categoryName[$cat]['name'])) {
    $title = ' - '.$category->categoryName[$cat]['name'];
}
*/

//
// Found an action request?
//
if (!isset($allowedVariables[$action])) {
    $action = 'main';
}

//
// Select the template for the requested page
//
if (isset($auth)) {
    $login_tpl = 'loggedin.tpl';
} else {
    if (isset($_SERVER['HTTPS']) || !$faqconfig->get('main.useSslForLogins')) {
        $login_tpl = 'loginbox.tpl';
    } else {
        $login_tpl = 'secureswitch.tpl';
    }
}

if ($action != 'main') {
    $inc_tpl         = $action . '.tpl';
    $inc_php         = $action . ".php";
    $writeLangAdress = "?" . PMF_Filter::getFilteredQueryString();
} else {
    if (isset($solution_id) && is_numeric($solution_id)) {
        // show the record with the solution ID
        $inc_tpl = 'artikel.tpl';
        $inc_php = 'artikel.php';
    } else {
        $inc_tpl = 'main.tpl';
        $inc_php = 'main.php';
    }
	if($faqconfig->get('main.useAjaxMenu')) {
		$writeLangAdress = "?";
	}
	else {
		$writeLangAdress = '?'.(int)$sids;
	}
}

//
// Set right column
//
// Check in any tags with at leat one entry exist
$hasTags = $oTag->existTagRelations();
if ($hasTags && (($action == 'artikel') || ($action == 'show'))) {
    $right_tpl = $action == 'artikel' ? 'catandtag.tpl' : 'tagcloud.tpl';
} else {
    $right_tpl = 'startpage.tpl';
}

//
// Load template files and set template variables
// Check on mobile devices first, if iPhone detected, switch to iPhone layout
//
/*
if (stristr($_SERVER['HTTP_USER_AGENT'], 'iPhone')) {
	$templateSet = 'mobile';
} else {
	$templateSet = $faqconfig->get('main.templateSet');
}
*/
$tpl = new PMF_Template(array('index'        => 'index.tpl',
                              'loginBox'     => $login_tpl,
                              'rightBox'     => $right_tpl,
                              'writeContent' => $inc_tpl),
                              $faqconfig->get('main.templateSet'));

$tpl->ajax_active = $faqconfig->get('main.useAjaxMenu');

if ($tpl->ajax_active) {
    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET": 
            $tpl->ajax_request = PMF_Filter::filterInput(INPUT_GET, 'ajax', FILTER_SANITIZE_STRING);
            break;
        case "POST": 
            $tpl->ajax_request = PMF_Filter::filterInput(INPUT_POST, 'ajax', FILTER_SANITIZE_STRING);
            break;
    }

    if ($tpl->ajax_request&&$tpl->ajax_request!='ajax_init'){
        // If it's not the ajax initialization, set the request
        switch($_SERVER['REQUEST_METHOD']) {
            case "GET": 
                $true_request = PMF_Filter::filterInput(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
                break;
            case "POST": 
                $true_request = PMF_Filter::filterInput(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
                break;
        }
        
        //If it's a login or logout request, reload only the login box
        if ($true_request=="login" || $true_request=="logout") {
            $tpl->ajax_request=$true_request;
        } else {
            $tpl->ajax_request=$action;
        }
    }

    if (strstr($writeLangAdress, "&")) {
        $writeLangAdress.= '&change_lang=true';
    } elseif (strstr($writeLangAdress, "?")) {
        $writeLangAdress.= 'change_lang=true';
    } else{ 
        $writeLangAdress.= '?change_lang=true';
    }

    $tpl->change_lang = PMF_Filter::filterInput(INPUT_POST, 'change_lang', FILTER_SANITIZE_STRING);

    //Associate a action request with template blocks
    $all_action = '(main|'.implode('|', array_keys($allowedVariables)).')';
    $tpl->varAjax = array('showCategories'	=> 'ajax_init',
                          'title'			=> $all_action,
                          'writeContent'	=> $all_action,
                          'writeLangAdress' => $all_action,
                          'action' 			=> $all_action,
                          'userOnline' 		=> $all_action,
                          'loginBox'		=> '(login|logout)',
                          'rightBox'		=> 'artikel');

    if (DEBUG) {
        $tpl->varAjax['debugMessages'] = $all_action;
        //If debug mode active reload also debug messages
    }

    //Init the ajax template map and store the data in session for better performance
    if (isset($_SESSION['parsedTemplates'])&&$tpl->ajax_request) {
        $tpl->parsedTemplates = $_SESSION['parsedTemplates'];
    } else {
        $tpl->TemplateAjaxInit();
        $_SESSION['parsedTemplates'] = $tpl->parsedTemplates;
    }
}

$usersOnLine    = getUsersOnline();
$totUsersOnLine = $usersOnLine[0] + $usersOnLine[1];
$systemUri      = PMF_Link::getSystemUri('index.php');

$categoryTree   = new PMF_Category_Tree($categoryData);

// If it's an ajax request , get the whole tree else get the filtered tree
if ($tpl->ajax_active && $tpl->ajax_request == 'ajax_init') {
    $categoryLayout = new PMF_Category_Layout(new PMF_Category_Tree_Helper($categoryTree));
} else {
    $categoryLayout = new PMF_Category_Layout(
        new PMF_Category_Tree_Helper(
            new PMF_Category_Path($categoryTree, $categoryPath)));
}

$keywordsArray = array_merge(explode(',', $keywords), explode(',', $faqconfig->get('main.metaKeywords')));
$keywordsArray = array_filter($keywordsArray, 'strlen');
shuffle($keywordsArray);
$keywords = implode(',', $keywordsArray);

$main_template_vars = array(
    'title'               => $faqconfig->get('main.titleFAQ').$title,
    'baseHref'            => $systemUri,
    'version'             => $faqconfig->get('main.currentVersion'),
    'header'              => str_replace('"', '', $faqconfig->get('main.titleFAQ')),
    'metaTitle'           => str_replace('"', '', $faqconfig->get('main.titleFAQ')),
    'metaDescription'     => $metaDescription,
    'metaKeywords'        => $keywords,
    'metaPublisher'       => $faqconfig->get('main.metaPublisher'),
    'metaLanguage'        => $PMF_LANG['metaLanguage'],
    'metaCharset'         => 'utf-8', // backwards compability
    'phpmyfaqversion'     => $faqconfig->get('main.currentVersion'),
    'stylesheet'          => $PMF_LANG['dir'] == 'rtl' ? 'style.rtl' : 'style',
    'action'              => $action,
    'dir'                 => $PMF_LANG['dir'],
    'msgCategory'         => $PMF_LANG['msgCategory'],
    'showCategories'      => $categoryLayout->renderNavigation($cat),
    'languageBox'         => $PMF_LANG['msgLangaugeSubmit'],
    'writeLangAdress'     => $writeLangAdress,
    'switchLanguages'     => PMF_Language::selectLanguages($LANGCODE, true),
    'userOnline'          => $plr->getMsg('plmsgUserOnline', $totUsersOnLine) .
                             $plr->getMsg('plmsgGuestOnline', $usersOnLine[0]) .
                             $plr->getMsg('plmsgRegisteredOnline',$usersOnLine[1]),
    'stickyRecordsHeader' => $PMF_LANG['stickyRecordsHeader'],
    'copyright'           => 'powered by <a href="http://www.phpmyfaq.de" target="_blank">phpMyFAQ</a> ' . 
                             $faqconfig->get('main.currentVersion'));

if ('main' == $action || 'show' == $action) {
    if ('main' == $action && PMF_Configuration::getInstance()->get('main.useAjaxSearchOnStartpage')) {
        $tpl->processBlock('index', 'globalSuggestBox', array('ajaxlanguage' => $LANGCODE));
    } else {
        $tpl->processBlock('index', 'globalSearchBox', array(
            'writeSendAdress' => '?'.$sids.'action=search',
            'searchBox'       => $PMF_LANG['msgSearch'],
            'categoryId'      => ($cat === 0) ? '%' : (int)$cat));
    }
}
                             
$stickyRecordsParams = $faq->getStickyRecords();
if (!isset($stickyRecordsParams['error'])) {
    $tpl->processBlock('index', 'stickyRecordsList', array(
        'stickyRecordsUrl'   => $stickyRecordsParams['url'],
        'stickyRecordsTitle' => $stickyRecordsParams['title']));
}

if ($faqconfig->get('main.enableRewriteRules')) {
    $links_template_vars = array(
        "faqHome"             => $faqconfig->get('main.referenceURL'),
        "msgSearch"           => '<a href="' . $systemUri . 'search.html">'.$PMF_LANG["msgAdvancedSearch"].'</a>',
        'msgAddContent'       => '<a href="' . $systemUri . 'addcontent.html">'.$PMF_LANG["msgAddContent"].'</a>',
        "msgQuestion"         => '<a href="' . $systemUri . 'ask.html">'.$PMF_LANG["msgQuestion"].'</a>',
        "msgOpenQuestions"    => '<a href="' . $systemUri . 'open.html">'.$PMF_LANG["msgOpenQuestions"].'</a>',
        'msgHelp'             => '<a href="' . $systemUri . 'help.html">'.$PMF_LANG["msgHelp"].'</a>',
        "msgContact"          => '<a href="' . $systemUri . 'contact.html">'.$PMF_LANG["msgContact"].'</a>',
        "backToHome"          => '<a href="' . $systemUri . 'index.html">'.$PMF_LANG["msgHome"].'</a>',
        "allCategories"       => '<a href="' . $systemUri . 'showcat.html">'.$PMF_LANG["msgShowAllCategories"].'</a>',
        'showInstantResponse' => '<a href="' . $systemUri . 'instantresponse.html">'.$PMF_LANG['msgInstantResponse'].'</a>',
        'showSitemap'         => '<a href="' . $systemUri . 'sitemap/A/'.$LANGCODE.'.html">'.$PMF_LANG['msgSitemap'].'</a>',
        'opensearch'          => $systemUri . 'opensearch.html');
} else {
    $links_template_vars = array(
        "faqHome"             => $faqconfig->get('main.referenceURL'),
        "msgSearch"           => '<a href="index.php?'.$sids.'action=search">'.$PMF_LANG["msgAdvancedSearch"].'</a>',
        "msgAddContent"       => '<a href="index.php?'.$sids.'action=add">'.$PMF_LANG["msgAddContent"].'</a>',
        "msgQuestion"         => '<a href="index.php?'.$sids.'action=ask">'.$PMF_LANG["msgQuestion"].'</a>',
        "msgOpenQuestions"    => '<a href="index.php?'.$sids.'action=open">'.$PMF_LANG["msgOpenQuestions"].'</a>',
        "msgHelp"             => '<a href="index.php?'.$sids.'action=help">'.$PMF_LANG["msgHelp"].'</a>',
        "msgContact"          => '<a href="index.php?'.$sids.'action=contact">'.$PMF_LANG["msgContact"].'</a>',
        "allCategories"       => '<a href="index.php?'.$sids.'action=show">'.$PMF_LANG["msgShowAllCategories"].'</a>',
        "backToHome"          => '<a href="index.php?'.$sids.'">'.$PMF_LANG["msgHome"].'</a>',
        'showInstantResponse' => '<a href="index.php?'.$sids.'action=instantresponse">'.$PMF_LANG['msgInstantResponse'].'</a>',
        'showSitemap'         => '<a href="index.php?'.$sids.'action=sitemap&amp;lang='.$LANGCODE.'">'.$PMF_LANG['msgSitemap'].'</a>',
        'opensearch'          => $systemUri . 'opensearch.php');
}

//
// Add debug info if needed
//
if (DEBUG) {
    $debug_template_vars = array(
        'debugMessages' => "\n".'<div id="debug_main">DEBUG INFORMATION:<br />'.$db->sqllog().'</div>'
    );
} else {
    $debug_template_vars = array('debugMessages' => '');
}

//
// Get main template, set main variables
//
$tpl->processTemplate('index', array_merge($main_template_vars, $links_template_vars, $debug_template_vars));

//
// Show login box or logged-in user information
//
if (isset($auth)) {
    $tpl->processTemplate('loginBox', array(
        'loggedinas'      => $PMF_LANG['ad_user_loggedin'],
        'currentuser'     => $user->getUserData('display_name'),
        'printAdminPath'  => (in_array(true, $permission)) ? 'admin/index.php' : '#',
        'adminSection'    => $PMF_LANG['adminSection'],
        'printLogoutPath' => '?action=logout',
        'logout'          => $PMF_LANG['ad_menu_logout']));
} else {
    if (isset($_SERVER['HTTPS']) || !$faqconfig->get('main.useSslForLogins')) {
        $tpl->processTemplate('loginBox', array(
            'writeLoginPath'  => '?action=login',
            'login'           => $PMF_LANG['ad_auth_ok'],
            'username'        => $PMF_LANG['ad_auth_user'],
            'password'        => $PMF_LANG['ad_auth_passwd'],
            'msgRegisterUser' => '<a href="?' . $sids . 'action=register">' . $PMF_LANG['msgRegisterUser'] . '</a>',
            'msgLoginFailed'  => $error));
    } else {
        $tpl->processTemplate('loginBox', array(
            'secureloginurl'  => sprintf('https://%s%s', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']),
            'securelogintext' => $PMF_LANG['msgSecureSwitch']));
    }
}
$tpl->includeTemplate('loginBox', 'index');

// generate top ten list
if ($faqconfig->get('main.orderingPopularFaqs') == 'visits') {
    
    // top ten list for most viewed entries
    $toptenParams = $faq->getTopTen('visits');
    if (!isset($toptenParams['error'])) {
        $tpl->processBlock('rightBox', 'toptenList', array(
            'toptenUrl'    => $toptenParams['url'],
            'toptenTitle'  => $toptenParams['title'],
            'toptenVisits' => $toptenParams['visits'])
        );
    } else {
        $tpl->processBlock('rightBox', 'toptenListError', array(
            'errorMsgTopTen' => $toptenParams['error'])
        );
    }
} else {

    // top ten list for most voted entries
    $toptenParams = $faq->getTopTen('voted');
    if (!isset($toptenParams['error'])) {
        $tpl->processBlock('rightBox', 'toptenList', array(
            'toptenUrl'    => $toptenParams['url'],
            'toptenTitle'  => $toptenParams['title'],
            'toptenVisits' => $toptenParams['voted'])
        );
    } else {
        $tpl->processBlock('rightBox', 'toptenListError', array(
            'errorMsgTopTen' => $toptenParams['error'])
        );
    }
}

$latestEntriesParams = $faq->getLatest();
if (!isset($latestEntriesParams['error'])) {
    $tpl->processBlock('rightBox', 'latestEntriesList', array(
        'latestEntriesUrl'   => $latestEntriesParams['url'],
        'latestEntriesTitle' => $latestEntriesParams['title'],
        'latestEntriesDate'  => $latestEntriesParams['date'])
    );
} else {
    $tpl->processBlock('rightBox', 'latestEntriesListError', array(
        'errorMsgLatest' => $latestEntriesParams['error'])
    );
}

$tpl->processTemplate('rightBox', array(
    'writeTopTenHeader'   => $PMF_LANG['msgTopTen'],
    'writeNewestHeader'   => $PMF_LANG['msgLatestArticles'],
    'writeTagCloudHeader' => $PMF_LANG['msg_tags'],
    'writeTags'           => $oTag->printHTMLTagsCloud(),
    'msgAllCatArticles'   => $PMF_LANG['msgAllCatArticles'],
    'allCatArticles'      => $faq->showAllRecordsWoPaging($cat))
);
$tpl->includeTemplate('rightBox', 'index');

//
// Include requested PHP file
//
require_once $inc_php;

//
// Send headers and print template
//
header("Expires: Thu, 07 Apr 1977 14:47:00 GMT");
header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-type: text/html; charset=utf-8");
header("Vary: Negotiate,Accept");

$tpl->printTemplate();

//
// Disconnect from database
//
$db->dbclose();

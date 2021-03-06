<?php
/**
 * Translation frontend for categories
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
 * @package   Administration
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @author    Rudi Ferrari <bookcrossers@gmx.de>
 * @copyright 2006-2010 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License Version 1.1
 * @link      http://www.phpmyfaq.de
 * @since     2006-09-10
 */

if (!defined('IS_VALID_PHPMYFAQ_ADMIN')) {
    header('Location: http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']));
    exit();
}

if ($permission["editcateg"]) {
    
    $categoryHelper = new PMF_Category_Helper();
    $categoryNode   = new PMF_Category_Node();
    
    // Set language
    $categoryNode->setLanguage($LANGCODE);
    
    $categoryId   = PMF_Filter::filterInput(INPUT_GET, 'cat', FILTER_VALIDATE_INT);
    $categoryData = $categoryNode->fetch($categoryId);
    $header       = sprintf('%s %s: <em>%s</em>',
        $PMF_LANG['ad_categ_trans_1'],
        $PMF_LANG['ad_categ_trans_2'],
        $categoryData->name);

    $selectedLanguage = PMF_Filter::filterInput(INPUT_POST, 'trlang', FILTER_SANITIZE_STRING, $LANGCODE);
    if ($selectedLanguage != $LANGCODE) {
        $showCategories = 'yes';
    } else {
        $showCategories = 'no';
    }

    printf('<h2>%s</h2>', $header);
?>
    <form action="?action=updatecategory" method="post">
    <fieldset>
    <legend><?php print $header; ?></legend>

        <input type="hidden" name="id" value="<?php print $categoryData->id; ?>" />
        <input type="hidden" name="parent_id" value="<?php print $categoryData->parent_id; ?>" />
        <input type="hidden" name="showcat" value="<?php print $showCategories; ?>" />

        <label class="left"><?php print $PMF_LANG["ad_categ_titel"]; ?>:</label>
        <input type="text" name="name" size="30" style="width: 250px;" value="" /><br />

        <label class="left"><?php print $PMF_LANG["ad_categ_lang"]; ?>:</label>
        <select name="lang" size="1">
        <?php print $categoryHelper->renderLanguages($categoryId, $selectedLanguage); ?>
        </select><br />

        <label class="left"><?php print $PMF_LANG["ad_categ_desc"]; ?>:</label>
        <input type="text" name="description" size="30" style="width: 250px;" value="" /><br />

        <label class="left"><?php print $PMF_LANG["ad_categ_owner"]; ?>:</label>
        <select name="user_id" size="1">
        <?php print $user->getAllUserOptions($categoryData->user_id); ?>
        </select><br />

        <input class="submit" style="margin-left: 190px;" type="submit" name="submit" value="<?php print $PMF_LANG["ad_categ_translatecateg"]; ?>" />
        <br /><hr />
<?php
    printf('<strong>%s</strong><br />', $PMF_LANG['ad_categ_transalready']);
    
    $categoryNode->setLanguage(null);
    foreach ($categoryNode->fetchAll(array($categoryId)) as $category) {
        printf("&nbsp;&nbsp;&nbsp;<strong style=\"vertical-align: top;\">&middot; %s</strong>: %s\n<br />",
            $languageCodes[strtoupper($category->lang)],
            $category->name);
    }
?>
    </fieldset>
    </form>
<?php
} else {
    print $PMF_LANG["err_NotAuth"];
}

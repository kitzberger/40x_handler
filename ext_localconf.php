<?php

if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['fox_handler']['404_page']) ||
    !empty($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['fox_handler']['403_pages'])) {

    // Register legacy 40x handler
    $GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'] = 'USER_FUNCTION:Kitzberger\FourOhExHandler\LegacyHandler->pageNotFound';
}

<?php

$GLOBALS['SiteConfiguration']['site_errorhandling']['columns']['404_page'] = [
    'label' => '404 page',
    'description' => 'single pid',
    'config' => [
        'type' => 'input',
    ],
];
$GLOBALS['SiteConfiguration']['site_errorhandling']['columns']['403_pages'] = [
    'label' => '403 pages',
    'description' => 'single pid or comma separated mapping of fe_group uids to page uids (e.g. 1=xxx,2=xxy)',
    'config' => [
        'type' => 'input',
    ],
    'displayCond' => 'FIELD:errorCode:=:403',
];

$GLOBALS['SiteConfiguration']['site_errorhandling']['palettes']['fox_handler'] = [
    'showitem' => '404_page, 403_pages',
];

$GLOBALS['SiteConfiguration']['site_errorhandling']['types']['PHP']['showitem'] = str_replace(
    'errorPhpClassFQCN',
    'errorPhpClassFQCN, --palette--;;fox_handler',
    $GLOBALS['SiteConfiguration']['site_errorhandling']['types']['PHP']['showitem']
);

<?php
$EM_CONF[$_EXTKEY] = [
	'title' => 'Fox Handler',
	'description' => 'This extension provides a pageNotFoundHandler that is able to handle both 404 and 403 responses.',
	'category' => 'system',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'author' => 'Philipp Kitzberger',
	'author_email' => 'typo3@kitze.net',
	'author_company' => '',
	'version' => '2.0.0',
	'constraints' => [
		'depends' => [
			'typo3' => '9.5.0-10.4.99',
		],
		'conflicts' => [],
		'suggests' => [],
	],
];

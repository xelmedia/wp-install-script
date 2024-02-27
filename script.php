<?php
require_once 'src/helper/scripts-helper.php';
$options = getopt("p:d:", ["projectName:", "domainName:"]);

if ((!isset($options['p']) || !isset($options['d'])) &&
    (!isset($options['projectName']) || !isset($options['domainName']))) {
    echo "Usage: php script.php -p <projectName> -d <domainName> OR php script.php --projectName=<projectName> --domainName=<domainName>\n";
    exit(1);
}

$projectName = $options['p'] ?? $options['projectName'];
$domainName = $options['d'] ?? $options['domainName'];
$helper = new ScriptsHelper(__DIR__."/cms", __DIR__."/.db.env");
$helper->installWpScripts($domainName, $projectName);


<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$target     = '/var/apps/coala_satis';
$configFile = $target . '/config.json';
$url        = 'git://git.coala.ch/';

$gitlabUrl    = rtrim('https://git.coala.ch', '/');
$gitlabKey    = '74ShxSiqjMLMKDhw6yJn';
$gitlabGroups = array(
    'AppBundles',
    'Bundles',
    'Puppet',
    'Frameworks',
    'Websites',
    'DrupalModules',
);

$composerHome = '/var/lib/composer';

if (!$url) {
    $url = $gitlabUrl;
}

function gitlabApi($url)
{
    global $gitlabUrl, $gitlabKey;

    $ch = curl_init($gitlabUrl . '/api/v3' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'PRIVATE-TOKEN: ' . $gitlabKey
    ));
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $result;
}

$config = array(
    'name'         => 'Coala',
    'homepage'     => 'https://packages.coala.ch',
    'repositories' => array(),
    'require-all'  => true
);

$p = 0;
do {
   $p++;
   $repositories = gitlabApi('/projects?per_page=100&page=' . $p);

   foreach ($repositories as $i => $repo) {
       if ($gitlabGroups && (!isset($repo['namespace']) || !in_array($repo['namespace']['name'], (array) $gitlabGroups))) {
           continue;
       }
       $composerJson = gitlabApi(sprintf('/projects/%d/repository/commits/master/blob?filepath=composer.json', $repo['id']));
       if (!$composerJson || isset($composerJson['message'])) {
           continue;
       }
       $config['repositories'][] = array(
           'type' => 'vcs',
           'url'  => sprintf('%s/%s.git', rtrim($url, '/'), $repo['path_with_namespace'])
       );
   }
} while(count($repositories) > 0);


file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

umask(0);
system('cd ' . $target . '; COMPOSER_HOME=' . $composerHome . ' php bin/satis -n --skip-errors -q build config.json web/', $exitCode);

exit($exitCode);

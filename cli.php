#!/usr/bin/env php
<?php
require_once(dirname(__FILE__) . '/Slog.php');
require_once(dirname(__FILE__) . '/CliParser.php');
require_once(dirname(__FILE__) . '/Slog/Formatter/Interface.php');

define("DEFAULT_DAYS", 14);
define("DEFAULT_LIMIT",     1000);  // set null to remove limit

$cli = new CliParser();
$cli->about('Wrapper around the SVN command line tool that provides '
                    . 'additional filtering capabilities such as '
                    . 'filtering by regex and author.')
    ->addOpt('a', 'author', 'Only show commits written by author. '
                    . 'Set this repeatedly to cast a larger net.', false, CliParser::TYPE_ARRAY, ',')
    ->addOpt('c', 'color', 'Use color to style output.', false, CliParser::TYPE_FLAG)
    ->addOpt('d', 'days', 'Number of days of data to fetch.')
    ->addOpt('e', 'regex', "Only show commits that match regex. Set this "
                    . "repeatedly to cast a larger net.\n"
                    . "e.g., /component/")
    ->addOpt('f', 'format', 'Format type: summary (default), oneline')
    ->addOpt('h', 'help', 'Show usage.', false, CliParser::TYPE_FLAG)
    ->addOpt('i', 'ignore', 'Exclude commits written by author. '
                    . 'Set this repeatedly to cast a larger net.', false, CliParser::TYPE_ARRAY, ',')
    ->addOpt('l', 'limit', 'Limit the number of commits to search')
    ->addOpt('r', 'repo', "SVN repository, e.g., http://svn.host.com/project.\n"
                    . "If not specified but CWD is a working copy, the working "
                    . "copy's repo will be used. If not in a working copy and "
                    . "an environment variable named SLOG_REPO exists, its "
                    . "value will be used.")
    ->addOpt('s', 'reverse', 'Print the most recent commits first.', false, CliParser::TYPE_FLAG)
    ->addOpt('v', 'verbose', 'Print debugging information.', false, CliParser::TYPE_FLAG)
    ->load($argv);

if ($cli->get('help')) {
    print $cli->getUsage();
    exit(0);
}


// Get repo for current directory ///////////////
$repo     = null;
$cmd      = "/usr/bin/env svn info 2>/dev/null";
$stdOut   = array();
$exitCode = null;
$lastLine = exec($cmd, $stdOut, $exitCode);
foreach ($stdOut as $line) {
    if (substr($line, 0, 5) == 'URL: ') {
        $repo = substr($line, 5);
        break;
    }
}
if (!$repo && isset($_SERVER['SLOG_DEFAULT_REPO'])) {
    $repo = $_SERVER['SLOG_DEFAULT_REPO'];
}

if (!$repo && !$cli->get('repo')) {
    print "Could not determine the SVN repo. Run this again inside an\n"
        . "SVN checkout or specify the repo from the command line.\n\n";
    exit(1);
}
// END: get repo ////////////////////////////////

$slog = new Slog($cli->get('repo',  $repo),
                 $cli->get('days',  DEFAULT_DAYS),
                 $cli->get('limit', DEFAULT_LIMIT));
if ($cli->get('verbose')) {
    $slog->setDebug(true);
}

if ($cli->get('author')) {
    $slog->matchAuthor($cli->get('author'));
}
if ($cli->get('ignore')) {
    $slog->removeCommitsFromAuthor($cli->get('ignore'));
}
if ($cli->get('regex')) {
    $slog->matchRegex($cli->get('regex'));
}

// Get the formatter
$format = ucfirst(strtolower($cli->get('format', 'summary')));
$formatClass = 'Slog_Formatter_' . $format;
$formatFile  = dirname(__FILE__) . '/Slog/Formatter/' . $format . '.php';
if (!is_readable($formatFile)) {
    print "Could not load formatter: $format.\n";
    exit(1);
}
require_once($formatFile);
$formatter = new $formatClass($cli);
$slog->setFormatter($formatter);
// END: get formatter


// Remove bot entries if desired
// $log->removeCommitsFromAuthor("some-bot-name");
print $slog->toString();

#!/usr/bin/env php
<?php
require_once(dirname(__FILE__) . '/Slog.php');
require_once(dirname(__FILE__) . '/CliParser.php');
require_once(dirname(__FILE__) . '/Slog/Formatter/Interface.php');

define("DEFAULT_DAYS",  0);     //default is no limit
define("DEFAULT_LIMIT", 2000);

$cli = new CliParser();
$cli->about('Wrapper around the svn log command line tool that provides '
                    . 'additional filtering capabilities such as '
                    . 'filtering by regex and author.')
    ->addOpt('a', 'author', 'Only show commits written by author. '
                    . 'Set repeatedly to cast a larger net.', false, CliParser::TYPE_ARRAY, ',')
    ->addOpt('c', 'color', 'Use color to style output.', false, CliParser::TYPE_FLAG)
    ->addOpt('d', 'days', 'Number of days of data to fetch. Set --days=0 to '
                    . 'remove limit. Default is ' . DEFAULT_DAYS . '.')
    ->addOpt('D', 'debug', 'Print debugging information.', false, CliParser::TYPE_FLAG)
    ->addOpt('e', 'regex', "Only show commits that match regex "
                    . "(e.g., /component/i). Set repeatedly to "
                    . "cast a larger net.")
    ->addOpt('f', 'format', "Format type: summary, shortsummary, oneline.\n"
                    . "Formats are defined in "
                    . dirname(__FILE__) . "/Slog/Formatter/*")
    ->addOpt('F', 'follow-copies', "Follow copies (i.e., follow the commit "
                    . "history all the way to the origin branch). Default is "
                    . "is to only follow commits on the current branch.", false, CliParser::TYPE_FLAG)
    ->addOpt('h', 'help', 'Show usage.', false, CliParser::TYPE_FLAG)
    ->addOpt('i', 'ignore', 'Exclude commits written by author. '
                    . 'Set this repeatedly to cast a larger net.', false, CliParser::TYPE_ARRAY, ',')
    ->addOpt('l', 'limit', 'Limit the number of commits that are transferred '
                    . 'from the server (default is ' . DEFAULT_LIMIT . '). '
                    . 'This improves performance by limiting the amount of '
                    . 'data that SVN sends over. However, note that filters '
                    . 'like --regex and --author are evaluated on the limited '
                    . 'commit log that it is received from the server. This '
                    . 'means that setting the limit to 10 and then also '
                    . 'applying an --author filter is likely to print less '
                    . 'than 10 commits. Set --limit=0 to remove the limit.')
    ->addOpt('o', 'repo', "SVN repository, e.g., http://svn.host.com/project.\n"
                    . "Otherwise if the current directory is an SVN working "
                    . "copy, the working copy's repo will be used.\n"
                    . "Otherwise if the environment variable SLOG_REPO is "
                    . "set, the SLOG_REPO variable will be used.")
    ->addOpt('R', 'reverse', 'Print the most recent commits first.', false, CliParser::TYPE_FLAG)
    ->load($argv);

if ($cli->get('help')) {
    print $cli->getUsage();
    exit(0);
}

$slog = new Slog();
if ($cli->get('repo')) {
    $slog->setRepo($cli->get('repo'));
}
if ($cli->get('limit') === null) {
    $slog->setLimit(DEFAULT_LIMIT);
} elseif ($cli->get('limit') != 0) {
    $slog->setLimit($cli->get('limit'));
}
if ($cli->get('days' === null)) {
    $slog->setLimit(DEFAULT_DAYS);
} elseif ($cli->get('days') != 0) {
    $slog->setDays($cli->get('days'));
}
<<<<<<< HEAD
// END: get repo ////////////////////////////////

$slog = new Slog($cli->get('repo',  $repo),
                 $cli->get('days',  DEFAULT_DAYS),
                 $cli->get('limit', DEFAULT_LIMIT));
=======
>>>>>>> Pass unrecognized CLI options to svn. Enabled stop on copy.
if ($cli->get('debug')) {
    $slog->setDebug(true);
}
if ($cli->get('author')) {
    $slog->matchAuthor($cli->get('author'));
}
if ($cli->get('ignore')) {
    // Use this to filter out bot commits, i.e., CSS minifiers etc.
    $slog->removeCommitsFromAuthor($cli->get('ignore'));
}
if ($cli->get('regex')) {
    $slog->matchRegex($cli->get('regex'));
}
if ($cli->get('follow-copies')) {
    $slog->setStopOnCopy(false);
}
if ($cli->getUnrecognizedOpts()) {
    // Pass any unrecognized options along to svn log
    $slog->setSvnOpts($cli->getUnrecognizedOpts());
}
$slog->setFormatterByName($cli->get('format', 'summary'), $cli);

print $slog->toString();

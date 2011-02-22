<?php

class Slog
{
    const DATE_FORMAT = "Y-m-d";
    
    /**
     * Commit log.
     *
     * @var SimpleXMLElement
     */
    private $data;
    
    /**
     * List of authors whose commits will be removed.
     * (Takes priority over mustMatchAuthors)
     *
     * @var array<string>
     */
    private $removeAuthors;
    
    /**
     * If set, only these authors' commits will be shown.
     *
     * @var array<string>
     */
    private $mustMatchAuthors;
    
    /**
     * If set, only commits that match these regular expressions will be shown.
     *
     * @var array<string>
     */
    private $mustMatchRegexes;

    /**
     * Set True to print debugging information.
     *
     * @var bool
     */
    private $debug;

    /**
     * Path to SVN repository.
     *
     * @var string
     */
    private $repo;

    /**
     * Number of days back of commit data to fetch from SVN.
     *
     * @var int
     */
    private $days;
    
    /**
     * Maximum number of commits to fetch from SVN.
     *
     * @var int
     */
    private $limit;

    /**
     * Whether or not to follow commit history beyond the current branch and
     * into the origin branch.
     *
     * @var bool
     */
    private $stopOnCopy;

    /**
     * String of command line options to send to svn log. This is intended to
     * store options that were unrecognized by Slog so that we can pass them
     * along to svn log instead.
     *
     * @var string
     */
    private $svnOpts;

    /**
     * Path to the SVN binary.
     *
     * @var string
     */
    private $svn;

    /**
     * Formatting implementation.
     *
     * @var SvnLog_Formatter_Interface
     */
    private $formatter;

    /**
     * @param int    $limit Max number of commits to fetch from the SVN repo.
     */
    public function __construct()
    {
        $this->debug = false;
        $this->removeAuthors = array();
        $this->mustMatchAuthors = array();
        $this->mustMatchRegexes = array();
        $this->repo = null;
        $this->days = null;
        $this->limit = null;
        $this->stopOnCopy = true;
        $this->svnOpts = '';
        $this->svn = '/usr/bin/env svn';
    }

    /**
     * Override the path to the SVN repo.
     *
     * @param string $path Filepath or URL to the SVN repo.
     *
     * @return Slog (supports fluent interface)
     */
    public function setRepo($path)
    {
        $this->repo = $repoUrl;
        return $this;
    }

    /**
     * Get repo for working copy at $path.
     *
     * @param string $path
     *
     * @return string|null URL for SVN repo associated with Working Copy at
     *                     $path.
     */
    public function getRepoFromWorkingCopy($path)
    {
        $repo     = null;
        $cmd      = "{$this->svn} info 2>/dev/null";
        $stdOut   = array();
        $exitCode = null;
        $lastLine = exec($cmd, $stdOut, $exitCode);
        foreach ($stdOut as $line) {
            if (substr($line, 0, 5) == 'URL: ') {
                $repo = substr($line, 5);
                break;
            }
        }
        // $repo will be null if svn couldn't get the repo URL for the
        // working copy at $path
        return $repo;
    }

    /**
     * Get path to SVN repo.
     * If repo was set with $slog->setRepo($repo), then $repo is used.
     * Otherwise if the CWD is a working copy, the working copy's repo is used.
     * Otherwise if the environment variable SLOG_DEFAULT_REPO exists, that is used.
     * If none of the above can be resolved, an exception is thrown.
     *
     * @throws Exception if none of the above methods are able to resolve the repo URL.
     *
     * @return string|null URL for SVN repo.
     */
    public function getRepo()
    {
        if (empty($this->repo)) {
            $this->repo = $this->getRepoFromWorkingCopy(getcwd());
        }
        if (empty($this->repo)) {
            if (isset($_SERVER['SLOG_DEFAULT_REPO'])) {
                $this->repo = $_SERVER['SLOG_DEFAULT_REPO'];
            }
        }
        if (empty($this->repo)) {
            throw new Exception("Could not determine the SVN repo. Its likely "
                    . "that the current directory is not a working copy.");
        }
        return $this->repo;
    }

    /**
     * Set the maximum number of days worth of historical data to fetch from svn log.
     *
     * @param int $days Maximum number of days worth of historical data to
     *                  fetch from svn log.
     *
     * @return Slog (supports fluent interface)
     */
    public function setDays($days)
    {
        $this->days = (int)$days;
        return $this;
    }

    /**
     * @return int Number of days of historical data to fetch from svn log.
     */
    public function getDays()
    {
        return $this->days;
    }

    /**
     * Set the max number of commits to fetch from the SVN server.
     *
     * @param int $limit Max number of commits to fetch from the SVN server.
     *
     * @return Slog (supports fluent interface)
     */
    public function setLimit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    /**
     * @return int Max number of commits to fetch from the SVN server.
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param bool $status Set true to enable debugging output.
     *
     * @return Slog (supports fluent interface)
     */
    public function setDebug($status = true)
    {
        $this->debug = (bool)$status;
        return $this;
    }

    /**
     * @param string $path Path to the SVN binary
     *
     * @return Slog (supports fluent interface)
     */
    public function setSvnBin($path)
    {
        $this->svn = $path;
        return $this;
    }

    private function debug($msg)
    {
        if ($this->debug) {
            print "[DEBUG] $msg\n";
        }
    }

    /**
     * Set whether or not to follow commits past the current branch to the origin.
     *
     * @param bool $bool Set true to only show commits from the current branch,
     *                   set false to show commits all the way back to origin.
     *
     * @return Slog (supports fluent interface)
     */
    public function setStopOnCopy($bool = true)
    {
        $this->stopOnCopy = (bool)$bool;
        return $this;
    }

    /**
     * Returns true if the Slog instance is configured to stop on copy.
     *
     * @return bool True if the Slog instance is configured to stop on copy.
     */
    public function getStopOnCopy()
    {
        return $this->stopOnCopy;
    }

    private function load()
    {
        if ($this->getDays()) {
            $startDate = date(self::DATE_FORMAT, strtotime("-{$this->getDays()} days"));
            $revision  = "--revision {{$startDate}}:HEAD";
        } else {
            $revision = '';
        }
        $limit      = $this->getLimit() ? "--limit {$this->getLimit()}" : '';
        $stopOnCopy = $this->getStopOnCopy() ? "--stop-on-copy" : '';
        $repo       = $this->getRepo();

        $cmd = "{$this->svn} log {$this->svnOpts} --xml {$revision} {$limit} {$stopOnCopy} -v {$repo} 2>&1";

        $this->debug("Executing command: {$cmd}");
        $xml = $this->fetchXml($cmd);
        $this->data = simplexml_load_string($xml);
    }

    /**
     * Fetches XML string from the output of a system call ($cmd).
     * This method is a helper method for load(), and is Implemented
     * as a separate method to facilitate unit testing (allowing us
     * to mock the Slog object and override this method in order to
     * return any XML string we want to test).
     *
     * @param string $cmd Shell command that will write XML string to stdout. 
     *
     * @return string XML string that written to stdout.
     */
    public function fetchXml($cmd)
    {
        $stdOut   = array();
        $exitCode = null;
        $lastLine = exec($cmd, $stdOut, $exitCode);
        $xml      = implode("\n", $stdOut);
        if ($exitCode) {
            throw new Exception("$lastLine\n($cmd)", $exitCode);
        }
        return $xml;
    }

    /**
     * Removes commits from author. Helpful for removing bot generated commits.
     *
     * @param string|array<string> $author Author(s) whose commits will be removed.
     *
     * @return Slog (supports fluent interface)
     */
    public function removeCommitsFromAuthor($author)
    {
        if (!is_array($author)) {
            $author = explode(',', $author);
        }
        $author = array_filter($author);
        foreach ($author as $a) {
            $this->removeAuthors[$a] = 1;
        }
        return $this;
    }
    
    /**
     * Only show commits from author.
     *
     * @param string|array<string> $author Author name to match.
     *
     * @return Slog (supports fluent interface)
     */
    public function matchAuthor($author)
    {
        if (!is_array($author)) {
            $author = explode(',', $author);
        }
        $author = array_filter($author);
        foreach ($author as $a) {
            $this->mustMatchAuthors[$a] = 1;
        }
        return $this;
    }
    
    /**
     * Ony show commits that match a regular expression.
     *
     * @param string|array<string> $pattern Pattern to match.
     *
     * @return Slog (supports fluent interface)
     */
    public function matchRegex($pattern)
    {
        if (!is_array($pattern)) {
            $pattern = array($pattern);
        }
        foreach ($pattern as $p) {
            // Regex patterns must be wrapped by non alpha-numeric and
            // non backslash characters. e.g., /pattern/ #pattern# ~pattern~
            // If the user provided a pattern that isn't delimited this way,
            // lets wrap the pattern with delimiters for them.
            if (preg_match("/[[:alnum:]\\\]/", $p[0])) {
                $delim = '/';
                $p     = $delim . preg_quote($p, $delim) . $delim;
            }
            $this->debug("Adding regex: $p");
            $this->mustMatchRegexes[] = $p;
        }
        return $this;
    }

    public function toString()
    {
        return $this->__toString();
    }
    
    public function __toString()
    {
        try {
            $this->load();
        } catch (Exception $e) {
            $msg = sprintf( "\nERROR(%s): %s\n\nSTACK TRACE:\n%s\n\n",
                $e->getCode(),
                $e->getMessage(),
                $e->getTraceAsString()
            );
            return $msg;
        }

        $msg = array();
        foreach($this->data->logentry as $commit) {
            if ($this->filter($commit)) {
                $msg[] = $commit;
            }
        }
        return $this->getFormatter()->format($msg);
    }

    private function filter($commit)
    {
        if ($this->removeAuthors) {
            if (isset($this->removeAuthors[(string)$commit->author])) {
                return false;
            }
        }
        if ($this->mustMatchAuthors) {
            if (!isset($this->mustMatchAuthors[(string)$commit->author])) {
                return false;
            }
        }
        if ($this->mustMatchRegexes) {
            $paths = '';
            foreach ($commit->paths->path as $path) {
                $paths .= $path['action'] . ' ' . $path . "\n";
            }
            $subject = implode("\n", array(
                        $commit['revision'],
                        $commit->author,
                        date("D, d M Y h:i A", strtotime((string)$commit->date)),
                        $paths,
                        $commit->msg));
            foreach ($this->mustMatchRegexes as $pattern) {
                if (!preg_match($pattern, $subject)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Loads and sets the formatter to be used for output, based on
     * a short name and a cli object (for style preferences).
     *
     * @param string    $name Short name of the Formatter class to load.
     * @param CliParser $cli  Command line options to use (for style preferences)
     *
     * @return Slog (supports fluent interface)
     */
    public function setFormatterByName($name, CliParser $cli)
    {
        $format = ucfirst(strtolower($name));
        $class  = 'Slog_Formatter_' . $format;
        $file   = dirname(__FILE__) . "/Slog/Formatter/{$format}.php";
        if (!is_readable($file)) {
            throw new Exception("Could not load formatter: $format. Tried to load from $file.");
        }
        require_once($file);
        $formatter = new $class($cli);
        $this->setFormatter($formatter);
        return $this;
    }

    /**
     * Set the formatter to be used by the log printer.
     * @see setFormatterByName()
     *
     * @param SvnLog_Formatter_Interface $formatter Formatting implementation
     *                                              to use for printing the
     *                                              commit log.
     *
     * @return Slog (supports fluent interface)
     */
    public function setFormatter(Slog_Formatter_Interface $formatter)
    {
        $this->formatter = $formatter;
        return $this;
    }

    /**
     * Returns the formatter.
     *
     * @return SvnLog_Formatter (Or null if it hasn't been set)
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * Set string of additional command line opts to send to svn log.
     *
     * @param string $opts String of additional command line opts to send to svn log.
     *
     * @return Slog (supports fluent interface)
     */
    public function setSvnOpts($opts)
    {
        $this->svnOpts = $opts;
        return $this;
    }

    /**
     * Returns the string of additional command line opts to send to svn log.
     *
     * @return String of additional command line opts to send to svn log.
     */
    public function getSvnOpts()
    {
        return $this->svnOpts;
    }

}

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
     * @param string $repo  Path to the SVN repo.
     * @param int    $days  Fetch commits submitted within this number of days.
     * @param int    $limit Max number of commits to fetch from the SVN repo.
     */
    public function __construct($repo, $days, $limit)
    {
        $this->debug = false;
        $this->removeAuthors = array();
        $this->mustMatchAuthors = array();
        $this->mustMatchRegexes = array();
        $this->repo = $repo;
        $this->days = $days;
        $this->limit = $limit;
        $this->svn = '/usr/bin/env svn';
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
    
    private function load($repo, $days, $limit)
    {
        $startDate = date(self::DATE_FORMAT, strtotime("-$days days"));
        $cmd = sprintf(
            "%s log --xml -r {%s}:HEAD -v %s 2>&1",
            $this->svn,
            $startDate, 
            $repo
        );
        if ($limit) {
            $cmd .= " --limit $limit";
        }
        $this->debug("Executing cmd: $cmd");
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
     * @return void
     */
    public function removeCommitsFromAuthor($author)
    {
        if (!is_array($author)) {
            $author = explode(',', $author);
        }
        $author = array_filter($author);
        if (empty($author)) {
            return;
        }
        foreach ($author as $a) {
            $this->removeAuthors[$a] = 1;
        }
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
        if (empty($author)) {
            return;
        }
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
        if (is_array($pattern)) {
            foreach ($pattern as $p) {
                $this->mustMatchRegexes[] = $p;
            }
        } else {
            $this->mustMatchRegexes[] = $pattern;
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
            $this->load($this->repo, $this->days, $this->limit);
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
     * Set the formatter to be used by the log printer.
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
    
}

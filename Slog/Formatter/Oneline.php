<?php

class Slog_Formatter_OneLine implements Slog_Formatter_Interface
{
    private $terminalWidth = 80;
    private $color         = false;
    private $reverse       = false;

    public function __construct($cli)
    {
        $this->color   = $cli->get('color', false);
        $this->reverse = $cli->get('reverse', false);

        // Get width of terminal (in columns)
        $stdOut   = array();
        $exitCode = null;
        $lastLine = exec('tput cols', $stdOut, $exitCode);
        if (!$exitCode) {
            $this->terminalWidth = (int) $lastLine;
        }
    }

    public function format(array $commits)
    {
        $msg = array();
        foreach ($commits as $commit) {
            $colorYellow = "\033[0;33m";
            $colorNone   = "\033[0m";

            if ($this->color) {
                $format = "{$colorYellow}r%s{$colorNone} %s\n";
            } else {
                $format = "r%s %s\n";
            }
            $trimMsg = trim($commit->msg);
            $msgLines = explode("\n", $trimMsg);
            $out = sprintf($format, $commit['revision'], $msgLines[0]);
            $msg[] = $out;
        }
        if ($this->reverse) {
            rsort($msg, SORT_NATURAL);
        }
        return implode('', $msg);
    }
}

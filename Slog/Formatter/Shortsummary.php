<?php

class Slog_Formatter_ShortSummary implements Slog_Formatter_Interface
{
    private $color;
    private $reverse;

    public function __construct($cli)
    {
        $this->color   = $cli->get('color', false);
        $this->reverse = $cli->get('reverse', false);
    }

    public function format(array $commits)
    {
        $msg   = array();
        $delim = str_repeat('-', 80) . "\n";
        foreach ($commits as $commit) {
            $colorYellow     = "\033[0;33m";
            $colorYellowBold = "\033[1;33m";
            $colorBold       = "\033[1m";
            $colorNone       = "\033[0m";

            if ($this->color) {
                $format = "{$colorYellowBold}r%s{$colorNone} | %s | %s\n";
            } else {
                $format = "r%s | %s | %s\n";
            }
            $out = sprintf(
                $format,
                $commit["revision"],
                $commit->author,
                date("D, d M Y h:i A", strtotime((string)$commit->date))
            );
            $out .= "\n" . wordwrap(trim($commit->msg), 80) . "\n\n";
            $msg[] = $out;
        }

        if ($this->reverse) {
            rsort($msg, SORT_NATURAL);
        }
        return $delim . implode($delim, $msg) . $delim;
    }
}

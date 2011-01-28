<?php

interface Slog_Formatter_Interface
{
    /**
     * @param array<SimpleXMLElement> $commits List of commits to format
     *
     * @return string Formatted list of commits.
     */
    public function format(array $commits);
}

<?php

/**
 * Utility for parsing arguments passed into a program via the command line.
 */
class CliParser
{
    /**
     * @var int Indicates an input option is required to be provided as a
     *          key/value pair (i.e., "--foo=bar" or "--foo bar")
     */
    const TYPE_VALUE = 1;

    /**
     * @var int Indicates an input option is a flag and does not have an
     *          accompanying value. (i.e., "--foo" or "-f")
     */
    const TYPE_FLAG  = 2;

    /**
     * Map of args provided on the command line, keyed by the short name.
     *
     * @var array<string:string>
     */
    private $opts = array();

    /**
     * Map of specifications for allowable parameters, keyed by short name.
     *
     * @var array<string:complex>
     */
    private $spec = array();

    /**
     * Map of long names to short names.
     *
     * @var array<string:string>
     */
    private $longNameMap = array();

    /**
     * Script name (for display in usage message)
     *
     * @var string
     */
    private $script = '';

    /**
     * Description of the tool for display in usage message.
     *
     * @var string
     */
    private $about = '';

    /**
     * Length of the longest long name.
     *
     * @var int
     */
    private $maxNameLength = 0;

    public function about($msg)
    {
        $this->about = $msg;
        return $this;
    }

    /**
     * Inform the parser of an option that your program accepts.
     *
     * @var string $short    Single letter short name for the option.
     * @var string $long     Multi letter long name for the option.
     * @var string $help     Message to display in usage output.
     * @var bool   $required True if the user is required to set this option.
     * @var int    $type     self::TYPE_VALUE or self::TYPE_FLAG
     *
     * @return Opt (supports fluent interface)
     */
    public function addOpt($short, $long, $help, $required=false, $type=self::TYPE_VALUE)
    {
        $this->longNameMap[$long] = $short;
        if ($this->maxNameLength == 0 || strlen($long) > $this->maxNameLength) {
            $this->maxNameLength = strlen($long);
        }
        $this->spec[$short] = array(
                'short'    => $short,
                'long'     => $long,
                'help'     => $help,
                'required' => $required,
                'type'     => $type);
        return $this;
    }

    public function load(array $argv)
    {
        $argc = count($argv);
        $this->script = $argv[0];
        for ($i=1; $i < $argc; $i++) {
            $arg = $argv[$i];
            $nextArg = ($i + 1 < $argc) ? $argv[$i + 1] : null;

            if (strpos($arg, '=') !== false) { // -a=foo, --author=foo
                $tmp   = explode('=', $arg);
                $name  = trim(trim($tmp[0]), '-');
                $value = trim($tmp[1]);
            } elseif ($nextArg && substr($nextArg, 0, 1) != '-') { // -a foo, --author foo
                $name  = trim(trim($arg), '-');
                $value = trim($nextArg);
                $i++;
            } else { // -v, --verbose
                $name  = trim(trim($arg), '-');
                $value = null;
            }
            $this->debug("load(): analyzing input \"$name\"=\"$value\"");

            foreach ($this->spec as $spec) {
                $this->debug("load():    comparing to option ({$spec['short']}, {$spec['long']})");
                if ($name == $spec['short'] || $name == $spec['long']) {
                    $this->debug("load():    MATCHES ({$spec['short']}, {$spec['long']})");
                    if ($spec['type'] == self::TYPE_FLAG) {
                        $this->opts[$spec['short']] = true;
                    } else {
                        $this->opts[$spec['short']] = $value;
                    }
                    break;
                }
            }
        }
        return $this;
    }

    public function get($name, $default=null)
    {
        if (isset($this->opts[$name])) {
            return $this->opts[$name];
        }
        if (isset($this->longNameMap[$name])
                && isset($this->opts[$this->longNameMap[$name]])) {
            return $this->opts[$this->longNameMap[$name]];
        }
        return $default;
    }

    public function getUsage()
    {
        $colorBold = "\033[1m";
        $colorNone = "\033[0m";
        $out = "\n{$colorBold}Usage{$colorNone}: script OPTIONS\n\n";
        if ($this->about) {
            $about = wordwrap($this->about, 80);
            $out .= "{$colorBold}ABOUT{$colorNone}\n$about\n\n";
        }
        $out .= "{$colorBold}OPTIONS{$colorNone}\n";
        foreach ($this->spec as $spec) {
            $shortPad = "    ";
            $longPad  = str_repeat($shortPad, 2);
            $valueDesc = $spec['type'] == self::TYPE_VALUE ? "=<{$spec['long']}>" : '';
            $out .= $shortPad. "-{$spec['short']}|--{$spec['long']}{$valueDesc}\n";
            $help = wordwrap($spec['help'], 80 - strlen($longPad));
            $out .= $longPad . str_replace("\n", "\n$longPad", $help) . "\n\n";
        }
        return $out;
    }

    public function toArray()
    {
        return $this->opts;
    }

    public function debug($msg)
    {
        return;
        print "[DEBUG] $msg\n";
    }
}

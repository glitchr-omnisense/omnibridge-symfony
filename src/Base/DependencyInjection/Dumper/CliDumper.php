<?php

namespace Base\DependencyInjection\Dumper;

use Symfony\Component\VarDumper\Cloner\Cursor;
use Symfony\Component\VarDumper\VarDumper;

class CliDumper extends \Symfony\Component\VarDumper\Dumper\CliDumper
{
    protected bool $debug = false;
    public function __construct($output = null, ?string $charset = null, int $flags = 0)
    {
        $this->debug = verbose_level() > 2;
        parent::__construct($output, $charset, $flags);
    }

    protected function style(string $style, string $value, array $attr = []): string
    {
        if(array_key_exists("file", $attr) && \array_key_exists("project_dir", $attr))
        {
            $attr["file"] = \relative_path($attr["file"], $attr["project_dir"]);
            unset($attr["project_dir"]);
        }
        
        return parent::style($style, $value, $attr);
    }

    protected array $customColors = [
        "green" => "0;32",
        "orange" => "0;33"
    ];

    public function dumpString(Cursor $cursor, string $str, bool $bin, int $cut): void
    {
        $firstKey = 0;
        $shortBacktrace = debug_backtrace_short();
        foreach ($shortBacktrace as $index => $trace) {
            if (strpos($trace, VarDumper::class . "::dump()") !== false) {
                $firstKey = $index + 1;
                break;
            }
        }

        if (preg_match('/^(.*):(\d+)(?:\s*>>\s*(.+))?$/', $shortBacktrace[$firstKey], $matches)) {
            $file = $matches[1] ?? '';
            $line = (int) $matches[2];
            $method = $matches[3] ?? '';
        }

        if($this->debug) {

            if ($this->colors ??= $this->supportsColors()) {      
                printf("\n\033[%smIn %s line %d\033[m\n", $this->customColors['orange'], basename($file), $line);
            } else {
                printf("\nIn %s line %d\n", $this->customColors['orange'], basename($file), $line);
            }
        }

        parent::dumpString($cursor, $str, $bin, $cut); 

        if($this->debug) {
            
            if ($this->colors ??= $this->supportsColors()) {
                printf("\n\033[%smDump trace:\033[m\n", $this->customColors['orange']);
            } else {
                printf("\nDump trace:\n");
            }

            $backtrace = debug_backtrace();
            foreach($backtrace as $key => $trace)
            {
                if($key < $firstKey) continue;
                if(isset($trace["file"]) && isset($trace["line"]))
                {
                    $relPath = explode(" >> ", $shortBacktrace[$key])[0] ?? '';
                    if($key === $firstKey) $method = "";
                    else $method = isset($trace["class"]) ? $trace["class"] . "::" . $trace["function"]."()" : $trace["function"]."()";

                    if ($this->colors ??= $this->supportsColors()) {
                        printf(" %s at \033[%sm%s\033[m\n", $method, $this->customColors['green'], $relPath);
                    } else {
                        printf(" %s at %s\n", $method, $relPath);
                    }
                }
            }

            print("\n");
        }
    }
}

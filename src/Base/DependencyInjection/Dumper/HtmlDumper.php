<?php

namespace Base\DependencyInjection\Dumper;
use Symfony\Component\VarDumper\VarDumper;

class HtmlDumper extends \Symfony\Component\VarDumper\Dumper\HtmlDumper
{
    protected function style(string $style, string $value, array $attr = []): string
    {
        if(array_key_exists("file", $attr) && \array_key_exists("project_dir", $attr))
        {
            $attr["file"] = \relative_path($attr["file"], $attr["project_dir"]);
            unset($attr["project_dir"]);
        }
        
        return parent::style($style, $value, $attr);
    }

    protected function dumpLine(int $depth, bool $endOfValue = false): void
    {
        $dumpPrefixBak = $this->dumpPrefix;
       
        $backtrace = debug_backtrace_short();
        $i = null;

        foreach ($backtrace as $index => $trace) {
            if (strpos($trace, VarDumper::class . "::dump()") !== false) {
                $i = $index + 1;
                break;
            }
        }

        $location = $i !== null ? $backtrace[$i] ?? null : null;
        if ($location) {
            $location = preg_match("/^(.+?)\:(\d+)/", $location, $matches);
            if ($location) $location = sprintf(" | 🖥️  Location: %s:%s", $matches[1], $matches[2]);
        }

        $this->dumpPrefix .= "<span>".\benchmark().$location."</span><hr>";
       
        parent::dumpLine($depth, $endOfValue);
        $this->dumpPrefix = $dumpPrefixBak;
    }
}

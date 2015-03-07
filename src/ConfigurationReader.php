<?php
namespace wapmorgan\HttpServer;

use \Exception;

class ConfigurationReader {
    public $listen;
    public $hostnames = [];

    public function __construct() {
        $file = file(dirname(dirname(__FILE__)).'/httpserver.configuration');
        $in_section = null;
        $in_rule = null;
        foreach ($file as $line) {
            $offset = strpos($line, substr(ltrim($line), 0, 1));
            $line = trim($line);
            if (empty($line) || $line[0] == '#') continue;

            if (substr(trim($line), -1) == ':' && strncmp($line, 'match', 5) !== 0) {
                $in_section = substr(trim($line), 0, -1);
                $in_rule = null;
            } else {
                if ($in_section === null)
                    throw new Exception('Cant use "'.$line.'" outside of section.');
                else if ($in_section == 'global') {
                    switch (strstr($line, ' ', true)) {
                        case 'listen':
                            $this->listen = rtrim(substr($line, strpos($line, ' ') + 1), ';');
                            break;
                    }
                } else {
                    if ($in_rule) {
                        if ($offset > $this->hostnames[$in_section]['match'][$in_rule]['_offset']) {
                            $this->hostnames[$in_section]['match'][$in_rule][strstr($line, ' ', true)] = trim(substr($line, strpos($line, ' ')), '; ');
                            continue;
                        } else {
                            $in_rule = null;
                        }
                    }
                    switch (strstr($line, ' ', true)) {
                        case 'document_root':
                            $this->hostnames[$in_section]['document_root'] = rtrim(substr($line, strpos($line, ' ') + 1), ';');
                            break;
                        case 'replace':
                            $this->hostnames[$in_section]['replace'][] = rtrim(substr($line, strpos($line, ' ') + 1), ';');
                            break;
                        case 'match':
                            $rule = trim(strstr(substr($line, strpos($line, ' ')), ':', true));
                            $this->hostnames[$in_section]['match'][$rule] = array('_offset' => $offset);
                            $in_rule = $rule;
                            break;
                        case 'compress':
                            $this->hostnames[$in_section]['compress'] = rtrim(substr($line, strpos($line, ' ') + 1), ' ;');
                            break;
                    }
                }
            }
        }
    }
}
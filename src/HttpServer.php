<?php
namespace wapmorgan\HttpServer;

class HttpServer {
    protected $configuration;
    protected $pathResolvers = [];

    public function __construct(ConfigurationReader $configuration) {
        $this->configuration = $configuration;
    }

    public function resolve($socket) {
        $writer = new SocketWriter($socket);
        try {
            $reader = new SocketReader($socket);
        } catch (HttpException $e) {
            $writer->writeCodeMessage($e->getCode(), $e->getMessage());
            $writer->writeHeader('Content-Type: text/html');
            $writer->writeContent('<html><body><h1>400 Bad Request</h1>Provided request is malformed</body></html>', true);
            return;
        }

        if ((!isset($reader->headers['Host']) || !isset($this->configuration->hostnames[$reader->headers['Host']])) && !isset($this->configuration->hostnames['default'])) {
            $writer->writeCodeMessage(503, 'Host is not found');
            $writer->writeHeader('Content-Type: text/html');
            $writer->writeContent('<html><body><h1>503 Host not found</h1>Selected host has not been found on this server</body></html>', true);
            return;
        }

        // resolving path
        $host = isset($reader->headers['Host']) && isset($this->configuration->hostnames[$reader->headers['Host']]) ? $this->configuration->hostnames[$reader->headers['Host']] : $this->configuration->hostnames['default'];
        if (isset($host['replace'])) {
            foreach ($host['replace'] as $replace_statement) {
                $replace_statement = explode(' ', $replace_statement);
                $replae_flags = isset($replace_statement[2]) ? explode(',', $replace_statement[2]) : [];
                // flags
                if (in_array('not_exist', $replae_flags))
                    if (file_exists($host['document_root'].$reader->path))
                        break;

                if (preg_match('~'.$replace_statement[0].'~u', $reader->path, $match)) {
                    $reader->path = preg_replace('~'.$replace_statement[0].'~', $replace_statement[1], $reader->path);
                    break;
                }
            }
        }

        $url = parse_url($reader->path);

        // if path points to directory
        if (substr($url['path'], -1) == '/' && strpos($url['path'], '?') === false) {
            $url['path'] = $this->probFiles($host['document_root'], $reader->path, ['index.php', 'index.html']);
            if (substr($url['path'], -1) == '/') {
                $writer->writeCodeMessage(404, 'Not found');
                $writer->writeHeader('Content-Type: text/html');
                $writer->writeContent('<html><body><h1>404 Not found</h1>Index file for this directory not found</body></html>', true);
                return;
            }
        }

        // default headers
        $default = array('Connection: close');

        // header modificators
        $modificators = array();
        if (isset($host['match'])) {
            foreach ($host['match'] as $match_expression => $match_modificators) {
                if (preg_match('~'.$match_expression.'~', $url['path'])) {
                    foreach ($match_modificators as $match_modificator => $match_modificator_arg) {
                        if ($match_modificator == '_offset') continue;
                        switch ($match_modificator) {
                            case 'cache':
                                switch (substr($match_modificator_arg, -1)) {
                                    case 'd':
                                        $modificators['cache'] = $match_modificator_arg * 86400;
                                        break;
                                    case 'h':
                                        $modificators['cache'] = $match_modificator_arg * 3600;
                                        break;
                                    case 'm':
                                        $modificators['cache'] = $match_modificator_arg * 60;
                                        break;
                                    case 's':
                                        $modificators['cache'] = (int)$match_modificator_arg;
                                        break;
                                }
                                break;
                        }
                    }
                }
            }
        }

        $choosed_encoding = null;
        if (isset($reader->headers['Accept-Encoding']) && isset($host['compress'])) {
            $accepted_encodings = array_map('trim', explode(',', $reader->headers['Accept-Encoding']));
            $available_encodings = explode(',', $host['compress']);
            if (in_array('deflate', $available_encodings) && version_compare(PHP_VERSION, '5.4.0', '<')) {
                fwrite(STDERR, "[warning] deflate compression can't be used on PHP prior to 5.4.0 due to gzencode behavior change.");
                unset($available_encodings[array_search('deflate', $available_encodings)]);
            }
            $used_encodings = array_intersect($available_encodings, $accepted_encodings);
            if (count($used_encodings) > 0) $choosed_encoding = $used_encodings[0];
        }

        if (file_exists($host['document_root'].$url['path'])) {
            // check for file type
            $extension = pathinfo($host['document_root'].$url['path'], PATHINFO_EXTENSION);
            switch ($extension) {
                case 'php':
                    ob_start();
                    $this->startUpPhpFile($host['document_root'].$url['path'], $reader, $url);
                    $content = ob_get_contents();
                    ob_end_clean();

                    foreach (headers_list() as $header) {
                        if (strncasecmp($header, 'http/', 5) === 0) {
                            header_remove($header);
                            preg_match('http/(1\.[01]) ([0-9]+) (.+?)', $header, $match);
                            $codeMessage = array($match[1], $match[2]);
                        }
                        if (strncasecmp(strstr($header, ':', true), 'Content-Type', 12) === 0) {
                            header_remove($header);
                            $contentType = substr(strstr($header, ':'), 1);
                        }
                    }
                    // default code message
                    if (empty($codeMessage)) $codeMessage = array(200, 'OK');
                    if (empty($contentType)) $contentType = 'text/html';
                    $writer->writeCodeMessage($codeMessage[0], $codeMessage[1]);
                    $writer->writeHeader('Content-Type: '.$contentType);

                    switch ($choosed_encoding) {
                        default:
                            $writer->writeHeader('Content-Length: '.strlen($content));
                            break;
                        case 'gzip':
                            $writer->writeHeader('Content-Encoding: gzip');
                            break;
                        case 'deflate':
                            $writer->writeHeader('Content-Encoding: deflate');
                            break;
                    }

                    foreach ($default as $dheader)
                        $writer->writeHeader($dheader);

                    foreach (headers_list() as $header) {
                        $writer->writeHeader($header);
                        header_remove($header);
                    }

                    switch ($choosed_encoding) {
                        default:
                            $writer->writeContent($content);
                            break;
                        case 'gzip':
                            $writer->writeContent(gzencode($content), true);
                            break;
                        case 'deflate':
                            $writer->writeContent(gzencode($content, FORCE_DEFLATE), true);
                            break;

                    }
                    break;
                default:
                    $writer->writeCodeMessage(200, 'OK');
                    // get file mime type
                    $mimetype = MimeTypeResolver::resolve($extension);
                    $writer->writeHeader('Content-Type: '.$mimetype);
                    $writer->writeHeader('Content-Length: '.filesize($host['document_root'].$url['path']));
                    $writer->writeHeader('Date: '.date('D, j M Y H:i:s e'));
                    $writer->writeHeader('Last-Modified: '.date('D, j M Y H:i:s e', filemtime($host['document_root'].$url['path'])));
                    foreach ($default as $dheader)
                        $writer->writeHeader($dheader);
                    if (isset($modificators['cache'])) {
                        $writer->writeHeader('Cache-Control: public, max-age='.$modificators['cache']);
                        $writer->writeHeader('Expires: '.date('D, j M Y H:i:s e', time() + $modificators['cache']));
                    }
                    $writer->writeContentFromFile($host['document_root'].$url['path']);
                    break;
            }
        } else {
            $writer->writeCodeMessage(404, 'Not found');
            $writer->writeHeader('Content-Type: text/html');
            $writer->writeContent('<html><body><h1>404 Not found</h1>File not found</body></html>', true);
            return;
        }
    }

    protected function probFiles($unchangablePath, $changablePath, $probes) {
        foreach ($probes as $probe) {
            if (file_exists($unchangablePath.$changablePath.$probe)) {
                return $changablePath.$probe;
            }
        }
        return $changablePath;
    }

    protected function findCodeMessage($headers) {
        foreach ($headers as $header) {
            if (strncasecmp($header, 'http/', 5) === 0) {
                preg_match('http/(1\.[01]) ([0-9]+) (.+?)', $header, $match);
                return array($match[1], $match[2]);
            }
        }
        return array(200, 'OK');
    }

    protected function startUpPhpFile($file, $reader, $url) {
        $_SERVER = array();
        $_SERVER += $reader->headers;
        $_GET = array();
        if (isset($url['query'])) parse_str($url['query'], $_GET);
        $_POST = array();
        if ($reader->method == 'POST')
            parse_str($_POST, $reader->content_buffer);
        $_COOKIE = array();
        if (isset($reader->headers['Cookie'])) {
            $cookies = explode('; ', $reader->headers['Cookie']);
            foreach ($cookies as $cookie) {
                $cookie = explode('=', $cookie, 2);
                $_COOKIE[$cookie[0]] = $cookie[1];
            }
        }

        include $file;
    }
}
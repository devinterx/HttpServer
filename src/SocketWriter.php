<?php
namespace wapmorgan\HttpServer;

class SocketWriter {
    protected $socket;

    public function __construct($socket) {
        $this->socket = $socket;
    }

    public function writeHeader($text) {
        socket_write($this->socket, $text."\n");
    }

    public function writeCodeMessage($code, $message) {
        socket_write($this->socket, 'HTTP/1.1 '.$code.' '.$message."\n");
    }

    public function writeContent($content, $countLength = false) {
        if ($countLength)
            socket_write($this->socket, 'Content-Length: '.strlen($content)."\n");
        socket_write($this->socket, "\n".$content);
    }

    public function writeContentFromFile($file) {
        $fp = fopen($file, 'rb');
        socket_write($this->socket, "\n");
        while (!feof($fp)) {
            socket_write($this->socket, fread($fp, 8192));
        }
        fclose($fp);
    }
}
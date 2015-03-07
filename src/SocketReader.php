<?php
namespace wapmorgan\HttpServer;

class SocketReader {
    public $method;
    public $path;
    public $protocol;
    public $headers = [];
    public $content_buffer;
    public $allowed_methods = array(
        'GET',
        'POST',
        'PUT',
        'DELETE',
    );

    /**
     * @Param resource $socket
     */
    public function __construct($socket, $maxSocketTimeout = 30) {
        // request header
        socket_set_block($socket);
        $line = explode(' ', socket_read($socket, 1024, PHP_NORMAL_READ));
        if (count($line) == 1)
            throw new HttpException(400, 'Bad Request');
        $this->method = $line[0];
        $this->path = $line[1];
        $this->protocol = $line[2];

        $start_time = time();
        while ((time() - $start_time) <= $maxSocketTimeout) {
            $b = socket_read($socket, 2048, PHP_NORMAL_READ);
            if ($b == "\n") continue;
            $header = explode(':', $b);
            if (count($header) == 1) {
                if (isset($this->headers['Content-Length'])) {
                    socket_read($socket, 1, PHP_NORMAL_READ);
                    $this->content_buffer = socket_read($socket, $this->headers['Content-Length']);
                }
                break;
            } else {
                $this->headers[$this->separatedCamelCase($header[0])] = trim($header[1]);
            }
        }
        socket_set_nonblock($socket);
    }

    protected function separatedCamelCase($string) {
        return implode('-', array_map('ucfirst', explode('-', $string)));
    }
}
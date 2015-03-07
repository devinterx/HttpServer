<?php
namespace wapmorgan\HttpServer;

class MimeTypeResolver {
    static public $pairs = [
        'html' => 'text/html',
        'js' => 'application/javascript'
        'css' => 'text/css',
        'mp4' => 'video/mp4',
    ];

    static public function resolve($extension) {
        if (isset(self::$pairs[$extension]))
            return self::$pairs[$extension];
        else
            return 'application/octet-stream';
    }
}
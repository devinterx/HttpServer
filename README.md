# HttpServer
This is a http server for PHP completely written in PHP - *HttpServer*.

# Why do you do this?
Because I can.

# What features does it support?
1. Different hostnames.
2. GET and POST requests.
3. PHP files starting (with properly filled _SERVER, _POST, _GET, _COOKIE) and static files downloading.
4. Forking on any request to avoid delays.
5. Compression gzip, deflate.

# Configuration
All configuration in file **httpserver.configuration**.
It has blocks and directives inside blocks.
For example, this is default configuration:
```
global:
    listen 80

wapcode.ru:
    document_root C:\Users\User\Documents;
    match *.(png|jpe?g|gif):
        cache 30d;
    replace (.+) /index.php?$1 not_exist;

default:
    document_root C:\Users\User\Documents;
    compress gzip,deflate;

global.conf:
    document_root C:\Users\User\global.conf;

```

Some special host names:

1. **global** - is not a hostname. It is server configuration.
2. **default** - is default hostname. If user did not provide host this host will be used.

Inside server configuration following directives available.

**listen**.
> Sets server port to listen on.

Inside a block following directives available.

**document_root**.
> Specifies root folder for hostname. Must not end with slash.

**replace**.
> Allows replace requested path with regular expressions. First argument is an regular expression. Second is a destination path. (Must start with slash). Last arg is flags. *not_exist* flag changes behavior of replacement: if requested path exist, even if path matches expression, replacement will not be performed.

**compress**.
> Sets available compression methods for response generation. Available: *gzip,defalte*.

**match**.
> Specifies additional directives for special path(s).

**cache**.
> Tells server to add Cache header in response.

# How can I test that?
Download, Unpack, Update with composer (`composer update`), and run bin/httpserver (`php bin/httpserver`)

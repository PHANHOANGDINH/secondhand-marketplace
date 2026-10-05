#!/bin/sh
set -eu
REQUEST_METHOD=GET SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping \
    cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

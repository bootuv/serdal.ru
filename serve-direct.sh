#!/bin/bash
# Start PHP built-in server with increased limits

php -d upload_max_filesize=200M \
    -d post_max_size=200M \
    -d memory_limit=512M \
    -d max_execution_time=600 \
    -d max_input_time=600 \
    -S 127.0.0.1:8000 \
    -t public

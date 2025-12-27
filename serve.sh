#!/bin/bash
# Start Laravel development server with increased upload limits

php -d upload_max_filesize=200M \
    -d post_max_size=200M \
    -d memory_limit=512M \
    -d max_execution_time=600 \
    artisan serve

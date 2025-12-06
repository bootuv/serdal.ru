#!/bin/bash
# Start Laravel development server with increased upload limits

php -d upload_max_filesize=100M \
    -d post_max_size=100M \
    -d memory_limit=256M \
    -d max_execution_time=300 \
    artisan serve

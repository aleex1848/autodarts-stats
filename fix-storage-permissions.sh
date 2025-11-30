#!/bin/bash

# Fix storage permissions for Laravel
# This script sets the correct permissions for Laravel storage directories

echo "Fixing storage permissions..."

# Set permissions for storage directory
chmod 775 storage

# Set permissions for framework directory and subdirectories
chmod 775 storage/framework
chmod 775 storage/framework/cache
chmod 775 storage/framework/sessions
chmod 775 storage/framework/testing
chmod 775 storage/framework/views

# Also fix logs directory if it exists
if [ -d "storage/logs" ]; then
    chmod -s storage/logs
    chmod 775 storage/logs
fi

# Remove sticky bit if it exists (the T flag)
chmod -s storage/framework
chmod -s storage/framework/cache
chmod -s storage/framework/sessions
chmod -s storage/framework/testing
chmod -s storage/framework/views

echo "Permissions fixed!"
echo ""
echo "Current permissions for framework:"
ls -la storage/framework/
echo ""
echo "Current permissions for logs:"
if [ -d "storage/logs" ]; then
    ls -la storage/logs/
fi

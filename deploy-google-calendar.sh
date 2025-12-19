#!/bin/bash

# Google Calendar Integration Deployment Script
# Run this script on your production server after uploading the code

echo "🚀 Starting Google Calendar Integration deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Install dependencies
echo -e "${YELLOW}Step 1: Installing dependencies...${NC}"
composer install --no-dev --optimize-autoloader
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Dependencies installed${NC}"
else
    echo -e "${RED}✗ Failed to install dependencies${NC}"
    exit 1
fi

# Step 2: Publish config
echo -e "${YELLOW}Step 2: Publishing Google Calendar config...${NC}"
php artisan vendor:publish --provider="Spatie\GoogleCalendar\GoogleCalendarServiceProvider" --force
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Config published${NC}"
else
    echo -e "${RED}✗ Failed to publish config${NC}"
    exit 1
fi

# Step 3: Run migrations
echo -e "${YELLOW}Step 3: Running migrations...${NC}"
php artisan migrate --force
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Migrations completed${NC}"
else
    echo -e "${RED}✗ Failed to run migrations${NC}"
    exit 1
fi

# Step 4: Clear all caches
echo -e "${YELLOW}Step 4: Clearing caches...${NC}"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
composer dump-autoload -o
echo -e "${GREEN}✓ Caches cleared${NC}"

# Step 5: Optimize for production
echo -e "${YELLOW}Step 5: Optimizing for production...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo -e "${GREEN}✓ Optimization completed${NC}"

# Step 6: Restart queue workers (if applicable)
echo -e "${YELLOW}Step 6: Restarting queue workers...${NC}"
php artisan queue:restart
echo -e "${GREEN}✓ Queue workers restarted${NC}"

echo ""
echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to your .env file"
echo "2. Set up OAuth credentials in Google Cloud Console"
echo "3. Add redirect URI: https://your-domain.com/google/calendar/callback"
echo ""
echo -e "${GREEN}Done! 🎉${NC}"

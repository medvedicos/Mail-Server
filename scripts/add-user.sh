#!/bin/bash

# Add Mail User Script
# This script adds a new email user to the mail server

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=================================="
echo "Add Mail User"
echo "=================================="
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}Error: .env file not found!${NC}"
    exit 1
fi

# Load environment variables
source .env

# Get user input
read -p "Email address (e.g., user@$MAIL_DOMAIN): " EMAIL
read -s -p "Password: " PASSWORD
echo ""
read -s -p "Confirm password: " PASSWORD_CONFIRM
echo ""

# Validate input
if [ -z "$EMAIL" ]; then
    echo -e "${RED}Error: Email address is required!${NC}"
    exit 1
fi

if [ -z "$PASSWORD" ]; then
    echo -e "${RED}Error: Password is required!${NC}"
    exit 1
fi

if [ "$PASSWORD" != "$PASSWORD_CONFIRM" ]; then
    echo -e "${RED}Error: Passwords do not match!${NC}"
    exit 1
fi

# Extract domain from email
DOMAIN="${EMAIL#*@}"

# Check if domain exists in database
DOMAIN_ID=$(docker-compose exec -T postgres psql -U $POSTGRES_USER -d $POSTGRES_DB -tAc \
    "SELECT id FROM virtual_domains WHERE name='$DOMAIN';")

if [ -z "$DOMAIN_ID" ]; then
    echo -e "${YELLOW}Domain $DOMAIN not found. Adding it...${NC}"
    docker-compose exec -T postgres psql -U $POSTGRES_USER -d $POSTGRES_DB -c \
        "INSERT INTO virtual_domains (name, active) VALUES ('$DOMAIN', true);"
    DOMAIN_ID=$(docker-compose exec -T postgres psql -U $POSTGRES_USER -d $POSTGRES_DB -tAc \
        "SELECT id FROM virtual_domains WHERE name='$DOMAIN';")
fi

# Generate password hash using Dovecot
PASSWORD_HASH=$(docker-compose exec -T dovecot doveadm pw -s BLF-CRYPT -p "$PASSWORD" | tr -d '\r\n')

# Add user to database
echo ""
echo "Adding user $EMAIL..."

docker-compose exec -T postgres psql -U $POSTGRES_USER -d $POSTGRES_DB -c \
    "INSERT INTO virtual_users (domain_id, email, password, quota, active) 
     VALUES ($DOMAIN_ID, '$EMAIL', '$PASSWORD_HASH', 1073741824, true) 
     ON CONFLICT (email) DO UPDATE 
     SET password = '$PASSWORD_HASH', updated_at = CURRENT_TIMESTAMP;"

echo ""
echo -e "${GREEN}✓${NC} User $EMAIL added successfully!"
echo ""
echo "User details:"
echo "  Email: $EMAIL"
echo "  Quota: 1GB"
echo "  Status: Active"
echo ""
echo "The user can now:"
echo "  - Login to Roundcube webmail at http://localhost:$WEBMAIL_PORT"
echo "  - Configure email client with these settings:"
echo "    IMAP: $HOSTNAME:993 (SSL/TLS)"
echo "    SMTP: $HOSTNAME:587 (STARTTLS)"
echo ""

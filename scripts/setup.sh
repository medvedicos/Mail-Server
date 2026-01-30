#!/bin/bash

# Mail Server Setup Script
# This script performs initial setup of the mail server

set -e

echo "=================================="
echo "Mail Server Setup Script"
echo "=================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}Error: .env file not found!${NC}"
    echo "Please copy .env.example to .env and configure it:"
    echo "  cp .env.example .env"
    echo "  nano .env"
    exit 1
fi

echo -e "${GREEN}✓${NC} Found .env configuration file"

# Load environment variables
source .env

echo ""
echo "Domain: $MAIL_DOMAIN"
echo "Hostname: $HOSTNAME"
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker is not installed!${NC}"
    echo "Please install Docker first: https://docs.docker.com/get-docker/"
    exit 1
fi

echo -e "${GREEN}✓${NC} Docker is installed"

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Error: Docker Compose is not installed!${NC}"
    echo "Please install Docker Compose first: https://docs.docker.com/compose/install/"
    exit 1
fi

echo -e "${GREEN}✓${NC} Docker Compose is installed"

# Create necessary directories
echo ""
echo "Creating necessary directories..."
mkdir -p data/postgres
mkdir -p data/redis
mkdir -p data/mail
mkdir -p data/spool
mkdir -p data/roundcube
mkdir -p data/dkim
mkdir -p ssl
mkdir -p logs

echo -e "${GREEN}✓${NC} Directories created"

# Generate self-signed SSL certificates (for testing)
echo ""
echo "Checking SSL certificates..."
if [ ! -f ssl/cert.pem ] || [ ! -f ssl/key.pem ]; then
    echo "Generating self-signed SSL certificates..."
    echo -e "${YELLOW}Note: For production, use Let's Encrypt certificates!${NC}"
    
    openssl req -new -newkey rsa:4096 -days 365 -nodes -x509 \
        -subj "/C=RU/ST=Moscow/L=Moscow/O=Mail Server/CN=$HOSTNAME" \
        -keyout ssl/key.pem \
        -out ssl/cert.pem
    
    echo -e "${GREEN}✓${NC} Self-signed certificates generated"
else
    echo -e "${GREEN}✓${NC} SSL certificates already exist"
fi

# Set correct permissions
echo ""
echo "Setting permissions..."
chmod 600 ssl/key.pem
chmod 644 ssl/cert.pem
chmod -R 777 data/

echo -e "${GREEN}✓${NC} Permissions set"

# Pull Docker images
echo ""
echo "Pulling Docker images (this may take a while)..."
docker-compose pull

echo -e "${GREEN}✓${NC} Docker images pulled"

# Start the mail server
echo ""
echo "Starting mail server containers..."
docker-compose up -d

echo ""
echo "Waiting for services to start..."
sleep 10

# Check container status
echo ""
echo "Checking container status..."
docker-compose ps

echo ""
echo "=================================="
echo -e "${GREEN}Mail Server Setup Complete!${NC}"
echo "=================================="
echo ""
echo "Default credentials:"
echo "  Email: admin@$MAIL_DOMAIN"
echo "  Password: changeme"
echo ""
echo -e "${YELLOW}IMPORTANT: Change the default password immediately!${NC}"
echo ""
echo "Access Roundcube webmail at:"
echo "  http://localhost:$WEBMAIL_PORT"
echo ""
echo "Next steps:"
echo "  1. Change the default admin password"
echo "  2. Configure DNS records (see docs/dns-records.md)"
echo "  3. Set up proper SSL certificates with Let's Encrypt"
echo "  4. Add additional email accounts with: ./scripts/add-user.sh"
echo "  5. Generate DKIM keys with: ./scripts/generate-dkim.sh"
echo ""
echo "To view logs:"
echo "  docker-compose logs -f"
echo ""
echo "To stop the server:"
echo "  docker-compose down"
echo ""

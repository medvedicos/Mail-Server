#!/bin/bash

# Restore Mail Server Script
# This script restores the mail server from a backup

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=================================="
echo "Mail Server Restore"
echo "=================================="
echo ""

# Check if backup directory is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Backup directory not specified!${NC}"
    echo "Usage: ./scripts/restore.sh <backup_directory>"
    echo ""
    echo "Available backups:"
    ls -1 backups/ 2>/dev/null || echo "  No backups found"
    exit 1
fi

BACKUP_DIR=$1

# Check if backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    echo -e "${RED}Error: Backup directory does not exist: $BACKUP_DIR${NC}"
    exit 1
fi

echo "Restoring from: $BACKUP_DIR"
echo ""

# Confirm restore
echo -e "${YELLOW}WARNING: This will overwrite existing data!${NC}"
read -p "Are you sure you want to continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Restore cancelled."
    exit 0
fi

# Stop mail server
echo ""
echo "Stopping mail server..."
docker-compose down

# Load environment variables
if [ ! -f .env ]; then
    echo -e "${RED}Error: .env file not found!${NC}"
    exit 1
fi

source .env

# Restore configuration
if [ -f "$BACKUP_DIR/config.tar.gz" ]; then
    echo ""
    echo "Restoring configuration..."
    tar -xzf "$BACKUP_DIR/config.tar.gz"
    echo -e "${GREEN}✓${NC} Configuration restored"
fi

# Restore mail data
if [ -f "$BACKUP_DIR/maildata.tar.gz" ]; then
    echo ""
    echo "Restoring mail data..."
    rm -rf data/mail/
    tar -xzf "$BACKUP_DIR/maildata.tar.gz"
    echo -e "${GREEN}✓${NC} Mail data restored"
fi

# Restore DKIM keys
if [ -f "$BACKUP_DIR/dkim.tar.gz" ]; then
    echo ""
    echo "Restoring DKIM keys..."
    rm -rf data/dkim/
    tar -xzf "$BACKUP_DIR/dkim.tar.gz"
    echo -e "${GREEN}✓${NC} DKIM keys restored"
fi

# Restore SSL certificates
if [ -f "$BACKUP_DIR/ssl.tar.gz" ]; then
    echo ""
    echo "Restoring SSL certificates..."
    rm -rf ssl/
    tar -xzf "$BACKUP_DIR/ssl.tar.gz"
    echo -e "${GREEN}✓${NC} SSL certificates restored"
fi

# Start mail server (without database first)
echo ""
echo "Starting database..."
docker-compose up -d postgres
sleep 5

# Restore database
if [ -f "$BACKUP_DIR/database.sql.gz" ]; then
    echo ""
    echo "Restoring database..."
    gunzip -c "$BACKUP_DIR/database.sql.gz" | docker-compose exec -T postgres psql -U $POSTGRES_USER $POSTGRES_DB
    echo -e "${GREEN}✓${NC} Database restored"
fi

# Start all services
echo ""
echo "Starting all services..."
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
echo -e "${GREEN}Restore Complete!${NC}"
echo "=================================="
echo ""
echo "Mail server has been restored from backup."
echo "All services should now be running."
echo ""
echo "To verify:"
echo "  docker-compose logs -f"
echo ""

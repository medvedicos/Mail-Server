#!/bin/bash

# Backup Mail Server Script
# This script creates a backup of the mail server data and configuration

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=================================="
echo "Mail Server Backup"
echo "=================================="
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}Error: .env file not found!${NC}"
    exit 1
fi

# Load environment variables
source .env

# Create backup directory
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "Creating backup in: $BACKUP_DIR"
echo ""

# Backup database
echo "Backing up database..."
docker-compose exec -T postgres pg_dump -U $POSTGRES_USER $POSTGRES_DB | gzip > "$BACKUP_DIR/database.sql.gz"
echo -e "${GREEN}✓${NC} Database backed up"

# Backup mail data
echo "Backing up mail data..."
tar -czf "$BACKUP_DIR/maildata.tar.gz" data/mail/ 2>/dev/null || true
echo -e "${GREEN}✓${NC} Mail data backed up"

# Backup DKIM keys
echo "Backing up DKIM keys..."
tar -czf "$BACKUP_DIR/dkim.tar.gz" data/dkim/ 2>/dev/null || true
echo -e "${GREEN}✓${NC} DKIM keys backed up"

# Backup SSL certificates
echo "Backing up SSL certificates..."
tar -czf "$BACKUP_DIR/ssl.tar.gz" ssl/ 2>/dev/null || true
echo -e "${GREEN}✓${NC} SSL certificates backed up"

# Backup configuration
echo "Backing up configuration files..."
tar -czf "$BACKUP_DIR/config.tar.gz" config/ .env
echo -e "${GREEN}✓${NC} Configuration backed up"

# Create backup info file
cat > "$BACKUP_DIR/backup-info.txt" <<EOF
Mail Server Backup
==================

Date: $(date)
Domain: $MAIL_DOMAIN
Hostname: $HOSTNAME

Contents:
- database.sql.gz: PostgreSQL database dump
- maildata.tar.gz: User mailbox data
- dkim.tar.gz: DKIM keys
- ssl.tar.gz: SSL certificates
- config.tar.gz: Configuration files and .env

Restore Instructions:
1. Stop the mail server: docker-compose down
2. Extract archives to respective directories
3. Restore database: gunzip -c database.sql.gz | docker-compose exec -T postgres psql -U $POSTGRES_USER $POSTGRES_DB
4. Start the mail server: docker-compose up -d
EOF

# Calculate backup size
BACKUP_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)

echo ""
echo "=================================="
echo -e "${GREEN}Backup Complete!${NC}"
echo "=================================="
echo ""
echo "Backup location: $BACKUP_DIR"
echo "Backup size: $BACKUP_SIZE"
echo ""
echo "Backup contains:"
echo "  - Database dump"
echo "  - Mail data"
echo "  - DKIM keys"
echo "  - SSL certificates"
echo "  - Configuration files"
echo ""
echo "To restore from this backup:"
echo "  ./scripts/restore.sh $BACKUP_DIR"
echo ""

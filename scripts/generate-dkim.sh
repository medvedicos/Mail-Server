#!/bin/bash

# Generate DKIM Keys Script
# This script generates DKIM keys for email authentication

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=================================="
echo "Generate DKIM Keys"
echo "=================================="
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}Error: .env file not found!${NC}"
    exit 1
fi

# Load environment variables
source .env

# Get domain input
if [ -z "$1" ]; then
    DOMAIN=$MAIL_DOMAIN
else
    DOMAIN=$1
fi

echo "Generating DKIM keys for domain: $DOMAIN"
echo ""

# Create DKIM directory if it doesn't exist
mkdir -p data/dkim/$DOMAIN

# Generate DKIM keys
echo "Generating RSA key pair..."
openssl genrsa -out data/dkim/$DOMAIN/private.key 2048
openssl rsa -in data/dkim/$DOMAIN/private.key -pubout -out data/dkim/$DOMAIN/public.key

echo -e "${GREEN}✓${NC} DKIM keys generated"

# Extract public key for DNS record
PUBLIC_KEY=$(grep -v "BEGIN PUBLIC KEY" data/dkim/$DOMAIN/public.key | grep -v "END PUBLIC KEY" | tr -d '\n')

# Set permissions
chmod 600 data/dkim/$DOMAIN/private.key
chmod 644 data/dkim/$DOMAIN/public.key

echo ""
echo "=================================="
echo -e "${GREEN}DKIM Keys Generated Successfully!${NC}"
echo "=================================="
echo ""
echo "DNS Record to add:"
echo ""
echo "Type: TXT"
echo "Name: mail._domainkey.$DOMAIN"
echo "Value: v=DKIM1; k=rsa; p=$PUBLIC_KEY"
echo ""
echo "Or in zone file format:"
echo ""
echo "mail._domainkey.$DOMAIN. IN TXT \"v=DKIM1; k=rsa; p=$PUBLIC_KEY\""
echo ""
echo -e "${YELLOW}Note: Add this DNS record to your domain's DNS settings${NC}"
echo ""
echo "Private key location: data/dkim/$DOMAIN/private.key"
echo "Public key location: data/dkim/$DOMAIN/public.key"
echo ""

# Save DNS record to file
cat > data/dkim/$DOMAIN/dns-record.txt <<EOF
DKIM DNS Record for $DOMAIN
============================

Type: TXT
Name: mail._domainkey.$DOMAIN
Value: v=DKIM1; k=rsa; p=$PUBLIC_KEY

Zone File Format:
mail._domainkey.$DOMAIN. IN TXT "v=DKIM1; k=rsa; p=$PUBLIC_KEY"
EOF

echo "DNS record also saved to: data/dkim/$DOMAIN/dns-record.txt"
echo ""

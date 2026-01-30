# DNS Configuration for sutulaya.lol Mail Server

This document describes the DNS records required for your mail server to work properly.

## Required DNS Records

### 1. A Record (Mail Server)
Points your mail subdomain to your server's IP address.

```
Type: A
Name: mail.sutulaya.lol
Value: YOUR_SERVER_IP
TTL: 3600
```

**Example:**
```
mail.sutulaya.lol.    IN    A    192.0.2.1
```

---

### 2. MX Record (Mail Exchange)
Tells other mail servers where to deliver emails for your domain.

```
Type: MX
Name: sutulaya.lol (or @)
Value: mail.sutulaya.lol
Priority: 10
TTL: 3600
```

**Example:**
```
sutulaya.lol.    IN    MX    10 mail.sutulaya.lol.
```

**Note:** The dot (.) at the end is important in zone files!

---

### 3. SPF Record (Sender Policy Framework)
Prevents email spoofing by specifying which servers can send emails from your domain.

```
Type: TXT
Name: sutulaya.lol (or @)
Value: v=spf1 mx a:mail.sutulaya.lol -all
TTL: 3600
```

**Example:**
```
sutulaya.lol.    IN    TXT    "v=spf1 mx a:mail.sutulaya.lol -all"
```

**SPF Explanation:**
- `v=spf1` - SPF version 1
- `mx` - Allow MX records to send email
- `a:mail.sutulaya.lol` - Allow the A record of mail.sutulaya.lol
- `-all` - Reject all other servers (strict mode)

Alternative for testing (less strict):
```
v=spf1 mx a:mail.sutulaya.lol ~all
```
- `~all` - Soft fail (allow but mark as suspicious)

---

### 4. DKIM Record (DomainKeys Identified Mail)
Adds a digital signature to your emails for authentication.

**First, generate DKIM keys:**
```bash
./scripts/generate-dkim.sh
```

Then add the record provided by the script:

```
Type: TXT
Name: mail._domainkey.sutulaya.lol
Value: v=DKIM1; k=rsa; p=YOUR_PUBLIC_KEY_HERE
TTL: 3600
```

**Example:**
```
mail._domainkey.sutulaya.lol.    IN    TXT    "v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA..."
```

**Note:** The public key will be very long. Some DNS providers may require you to split it into multiple strings.

---

### 5. DMARC Record (Domain-based Message Authentication)
Tells receiving servers what to do with emails that fail SPF or DKIM checks.

```
Type: TXT
Name: _dmarc.sutulaya.lol
Value: v=DMARC1; p=quarantine; rua=mailto:dmarc@sutulaya.lol; ruf=mailto:dmarc@sutulaya.lol; fo=1; adkim=s; aspf=s; pct=100
TTL: 3600
```

**Example:**
```
_dmarc.sutulaya.lol.    IN    TXT    "v=DMARC1; p=quarantine; rua=mailto:dmarc@sutulaya.lol; ruf=mailto:dmarc@sutulaya.lol; fo=1"
```

**DMARC Explanation:**
- `v=DMARC1` - DMARC version 1
- `p=quarantine` - Quarantine emails that fail checks
  - `p=none` - Monitor only (for testing)
  - `p=reject` - Reject failed emails (strict)
- `rua=mailto:dmarc@sutulaya.lol` - Send aggregate reports here
- `ruf=mailto:dmarc@sutulaya.lol` - Send forensic reports here
- `fo=1` - Generate reports for any failure
- `adkim=s` - Strict DKIM alignment
- `aspf=s` - Strict SPF alignment
- `pct=100` - Apply policy to 100% of emails

**Start with monitoring mode:**
```
v=DMARC1; p=none; rua=mailto:dmarc@sutulaya.lol
```

Then gradually move to:
- `p=quarantine` after 1-2 weeks
- `p=reject` after another 1-2 weeks

---

### 6. PTR Record (Reverse DNS)
Maps your server's IP address back to your mail domain. This is configured at your hosting provider, not your DNS registrar.

```
Type: PTR
IP: YOUR_SERVER_IP
Value: mail.sutulaya.lol
```

**Example:**
```
1.2.0.192.in-addr.arpa.    IN    PTR    mail.sutulaya.lol.
```

**Important:** Contact your hosting provider (VPS/dedicated server provider) to set up PTR records. Many providers allow you to configure this in their control panel.

---

## Complete DNS Configuration Example

```dns
; A Record for mail server
mail.sutulaya.lol.              IN    A       192.0.2.1

; MX Record
sutulaya.lol.                   IN    MX      10 mail.sutulaya.lol.

; SPF Record
sutulaya.lol.                   IN    TXT     "v=spf1 mx a:mail.sutulaya.lol -all"

; DKIM Record (generate key first!)
mail._domainkey.sutulaya.lol.   IN    TXT     "v=DKIM1; k=rsa; p=YOUR_PUBLIC_KEY"

; DMARC Record
_dmarc.sutulaya.lol.            IN    TXT     "v=DMARC1; p=quarantine; rua=mailto:dmarc@sutulaya.lol"

; Optional: Webmail subdomain
webmail.sutulaya.lol.           IN    A       192.0.2.1
```

---

## Verification

After adding DNS records, verify them:

### Check MX Record:
```bash
nslookup -type=mx sutulaya.lol
```

### Check SPF Record:
```bash
nslookup -type=txt sutulaya.lol
```

### Check DKIM Record:
```bash
nslookup -type=txt mail._domainkey.sutulaya.lol
```

### Check DMARC Record:
```bash
nslookup -type=txt _dmarc.sutulaya.lol
```

### Check PTR Record:
```bash
nslookup YOUR_SERVER_IP
```

---

## Online Verification Tools

- **MXToolbox:** https://mxtoolbox.com/
- **DKIM Validator:** https://dkimvalidator.com/
- **Mail-tester:** https://www.mail-tester.com/
- **DMARC Analyzer:** https://mxtoolbox.com/dmarc.aspx

---

## Propagation Time

DNS changes can take time to propagate:
- Typical: 15 minutes to 4 hours
- Maximum: 24-48 hours

Use `dig` or online tools to check propagation status.

---

## Troubleshooting

### Problem: MX record not resolving
- **Solution:** Wait for DNS propagation (up to 48 hours)
- **Check:** Verify the record is correctly added in your DNS provider's control panel

### Problem: SPF validation failing
- **Solution:** Ensure there are no typos in the SPF record
- **Check:** Use SPF validation tools online

### Problem: DKIM validation failing
- **Solution:** Verify the public key matches what was generated
- **Check:** Ensure no line breaks or extra spaces in the TXT record

### Problem: Cannot receive emails
- **Solution:** Check MX record, firewall rules (port 25)
- **Check:** Verify server IP is not blacklisted (https://mxtoolbox.com/blacklists.aspx)

### Problem: Emails going to spam
- **Solutions:**
  - Implement all DNS records (SPF, DKIM, DMARC)
  - Configure PTR record
  - Warm up your mail server (send gradually increasing volumes)
  - Avoid spam trigger words
  - Request whitelist from major providers if needed

---

## Security Best Practices

1. **Always use TLS/SSL** for mail connections
2. **Implement rate limiting** to prevent abuse
3. **Monitor DMARC reports** regularly
4. **Keep software updated**
5. **Use strong passwords** for all accounts
6. **Enable fail2ban** to prevent brute force attacks
7. **Regular backups** of mail data

---

For more help, visit: https://www.rfc-editor.org/rfc/rfc5321.html

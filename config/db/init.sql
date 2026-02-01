-- Mail Server Database Schema
-- PostgreSQL Database for Virtual Mail Users, Domains, and Aliases

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Domains table
CREATE TABLE IF NOT EXISTS virtual_domains (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table (email accounts)
CREATE TABLE IF NOT EXISTS virtual_users (
    id SERIAL PRIMARY KEY,
    domain_id INTEGER NOT NULL REFERENCES virtual_domains(id) ON DELETE CASCADE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    quota BIGINT DEFAULT 1073741824, -- 1GB in bytes
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_domain FOREIGN KEY (domain_id) REFERENCES virtual_domains(id)
);

-- Aliases table (email forwarding)
CREATE TABLE IF NOT EXISTS virtual_aliases (
    id SERIAL PRIMARY KEY,
    domain_id INTEGER NOT NULL REFERENCES virtual_domains(id) ON DELETE CASCADE,
    source VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(source, destination)
);

-- DKIM keys table
CREATE TABLE IF NOT EXISTS dkim_keys (
    id SERIAL PRIMARY KEY,
    domain_id INTEGER NOT NULL REFERENCES virtual_domains(id) ON DELETE CASCADE,
    selector VARCHAR(63) NOT NULL DEFAULT 'mail',
    private_key TEXT NOT NULL,
    public_key TEXT NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(domain_id, selector)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_virtual_users_email ON virtual_users(email);
CREATE INDEX IF NOT EXISTS idx_virtual_users_domain ON virtual_users(domain_id);
CREATE INDEX IF NOT EXISTS idx_virtual_users_active ON virtual_users(active);
CREATE INDEX IF NOT EXISTS idx_virtual_aliases_source ON virtual_aliases(source);
CREATE INDEX IF NOT EXISTS idx_virtual_aliases_destination ON virtual_aliases(destination);
CREATE INDEX IF NOT EXISTS idx_virtual_domains_name ON virtual_domains(name);

-- Insert default domain
INSERT INTO virtual_domains (name, active) 
VALUES ('sutulaya.lol', true)
ON CONFLICT (name) DO NOTHING;

-- Create a default admin user (password: changeme)
-- Password hash generated with: doveadm pw -s BLF-CRYPT -p changeme
-- Using $2a$ prefix for standard bcrypt compatibility with Dovecot
INSERT INTO virtual_users (domain_id, email, password, quota, active)
SELECT 
    d.id,
    'admin@sutulaya.lol',
    '{BLF-CRYPT}$2a$05$bvIG6Nmid91Mu9RcmmWZfO5HJIMCT8riNW0hEp8f6/FuA2/mHZFpe',
    5368709120, -- 5GB
    true
FROM virtual_domains d
WHERE d.name = 'sutulaya.lol'
ON CONFLICT (email) DO NOTHING;

-- Create some useful aliases
INSERT INTO virtual_aliases (domain_id, source, destination, active)
SELECT 
    d.id,
    'postmaster@sutulaya.lol',
    'admin@sutulaya.lol',
    true
FROM virtual_domains d
WHERE d.name = 'sutulaya.lol'
ON CONFLICT (source, destination) DO NOTHING;

INSERT INTO virtual_aliases (domain_id, source, destination, active)
SELECT 
    d.id,
    'abuse@sutulaya.lol',
    'admin@sutulaya.lol',
    true
FROM virtual_domains d
WHERE d.name = 'sutulaya.lol'
ON CONFLICT (source, destination) DO NOTHING;

INSERT INTO virtual_aliases (domain_id, source, destination, active)
SELECT 
    d.id,
    'hostmaster@sutulaya.lol',
    'admin@sutulaya.lol',
    true
FROM virtual_domains d
WHERE d.name = 'sutulaya.lol'
ON CONFLICT (source, destination) DO NOTHING;

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers to auto-update updated_at
CREATE TRIGGER update_virtual_domains_updated_at BEFORE UPDATE ON virtual_domains
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_virtual_users_updated_at BEFORE UPDATE ON virtual_users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_virtual_aliases_updated_at BEFORE UPDATE ON virtual_aliases
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Grant permissions
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO mailuser;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO mailuser;

# Infrastructure Provisioning Guide

## Overview
This runbook details how to provision a clean Ubuntu 24.04 server for RestaurantPOS Production/Staging environments.

## 1. Initial Server Setup
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install essential tools
sudo apt install -y curl wget git unzip ufw fail2ban supervisor
```

## 2. Docker & Docker Compose
We use Docker for the production environment to ensure consistency.

```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Add user to docker group
sudo usermod -aG docker $USER
```

## 3. Firewall (UFW)
```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## 4. Let's Encrypt (Certbot)
```bash
sudo apt install -y certbot
# Generate wildcard or specific certs (replace domain)
# sudo certbot certonly --standalone -d api.yourdomain.com
```

## 5. Deployment Steps
1. Clone the repository to `/var/www/RestaurantPOS`.
2. Copy `.env.example` to `.env` and fill securely from Secret Manager.
3. Run `docker-compose -f docker-compose.prod.yml up -d --build`.
4. Run deployment scripts inside the `app` container.

**Do Not Do**:
- Do not expose port 3306 or 6379 publicly.
- Do not run `php artisan migrate` (Follow SQL-first process).

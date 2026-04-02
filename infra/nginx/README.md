# Nginx Configuration

## Apply on VPS

### 1. Copy vhost configs

```bash
scp -P 22153 infra/nginx/sites-available/* deploy@186.202.209.180:/tmp/
```

### 2. Move to nginx sites-available

```bash
ssh -p 22153 deploy@186.202.209.180
sudo mv /tmp/*.conf /etc/nginx/sites-available/
```

### 3. Enable sites

```bash
chmod +x infra/nginx/scripts/enable-sites.sh
./infra/nginx/scripts/enable-sites.sh
```

### 4. Get SSL certificates

```bash
chmod +x infra/nginx/scripts/certbot-ssl.sh
sudo ./infra/nginx/scripts/certbot-ssl.sh
```

## Files

```
infra/nginx/
├── sites-available/
│   ├── stage.www.interazap.com.br
│   ├── stage.api.interazap.com.br
│   ├── api.interazap.com.br
│   ├── stage.app.interazap.com.br
│   ├── app.interazap.com.br
│   ├── stage.gateway.interazap.com.br
│   └── gateway.interazap.com.br
└── scripts/
    ├── enable-sites.sh
    └── certbot-ssl.sh
```

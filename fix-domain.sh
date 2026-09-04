#!/bin/bash

# 1. Update Nginx configuration strictly for project.rademics.ai
cat << 'EOF' > /etc/nginx/sites-available/projectai.rademics.ai
server {
    listen 80;
    server_name project.rademics.ai;

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 20M;
    }
}
EOF

# 2. Test and reload Nginx
echo "Testing Nginx..."
nginx -t && systemctl reload nginx

# 3. Issue SSL certificate only for project.rademics.ai
echo "Getting SSL Certificate for project.rademics.ai..."
certbot --nginx -d project.rademics.ai --redirect

echo "Done! project.rademics.ai is now secure and live!"

#!/bin/bash

# Initial setup script for KanbanFlow Dashboard on production server
# Run this once to set up the project on the server

echo "🚀 Starting initial setup for KanbanFlow Dashboard..."

# SSH into server and run setup commands
ssh caws2 << 'EOF'
    set -e  # Exit on any error
    
    echo "📂 Creating project directory..."
    sudo mkdir -p /var/www/kanbanflow-dashboard
    sudo chown -R www-data:www-data /var/www/kanbanflow-dashboard
    
    echo "📥 Cloning repository..."
    cd /var/www
    sudo -u www-data git clone https://github.com/tjanuki/kanbanflow-dashboard.git kanbanflow-dashboard || {
        echo "⚠️  Repository already exists or clone failed. Trying to pull latest..."
        cd kanbanflow-dashboard
        sudo -u www-data git pull origin main
    }
    
    cd /var/www/kanbanflow-dashboard
    
    echo "🔧 Setting up environment file..."
    if [ ! -f .env ]; then
        sudo -u www-data cp .env.example .env
        echo "📝 Please update the .env file with production settings after setup!"
    else
        echo "✅ .env file already exists"
    fi
    
    echo "📦 Installing composer dependencies..."
    sudo -u www-data composer install --no-dev --optimize-autoloader
    
    echo "🔑 Generating application key..."
    sudo -u www-data php artisan key:generate
    
    echo "🗄️ Creating MySQL database and user..."
    sudo mysql << 'MYSQL'
        CREATE DATABASE IF NOT EXISTS kanbanflow_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS 'kanbanflow_user'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
        GRANT ALL PRIVILEGES ON kanbanflow_dashboard.* TO 'kanbanflow_user'@'localhost';
        FLUSH PRIVILEGES;
MYSQL
    
    echo "⚠️  IMPORTANT: Update the database credentials in .env file:"
    echo "   DB_CONNECTION=mysql"
    echo "   DB_DATABASE=kanbanflow_dashboard"
    echo "   DB_USERNAME=kanbanflow_user"
    echo "   DB_PASSWORD=CHANGE_THIS_PASSWORD"
    
    echo "📂 Setting up storage directories..."
    sudo -u www-data mkdir -p storage/app/public
    sudo -u www-data mkdir -p storage/framework/{cache,sessions,testing,views}
    sudo -u www-data mkdir -p storage/logs
    sudo -u www-data mkdir -p bootstrap/cache
    
    echo "🔐 Setting proper permissions..."
    sudo chown -R www-data:www-data .
    sudo chmod -R 755 .
    sudo chmod -R 775 storage bootstrap/cache
    
    echo "🏗️ Installing NPM dependencies and building assets..."
    sudo -u www-data npm ci
    sudo -u www-data npm run build
    
    echo "🔗 Creating storage link..."
    sudo -u www-data php artisan storage:link
    
    echo "🗃️ Running migrations..."
    read -p "Have you updated the .env file with database credentials? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        sudo -u www-data php artisan migrate --force
        
        echo "🌱 Running seeders (if needed)..."
        read -p "Do you want to run database seeders? (y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            sudo -u www-data php artisan db:seed
        fi
    else
        echo "⚠️  Skipping migrations. Run 'php artisan migrate' after updating .env"
    fi
    
    echo "🔧 Optimizing Laravel..."
    sudo -u www-data php artisan config:cache
    sudo -u www-data php artisan route:cache
    sudo -u www-data php artisan view:cache
    
    echo "📝 Creating Nginx configuration..."
    sudo tee /etc/nginx/sites-available/kanbanflow-dashboard > /dev/null << 'NGINX'
server {
    listen 80;
    server_name YOUR_DOMAIN_HERE;
    root /var/www/kanbanflow-dashboard/public;

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Increase upload size for KanbanFlow attachments
    client_max_body_size 20M;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
}
NGINX
    
    echo "🔗 Enabling Nginx site..."
    sudo ln -sf /etc/nginx/sites-available/kanbanflow-dashboard /etc/nginx/sites-enabled/
    
    echo "✅ Testing Nginx configuration..."
    sudo nginx -t
    
    echo "🔄 Reloading services..."
    sudo systemctl reload nginx
    sudo systemctl reload php8.3-fpm
    
    echo "📋 Setup Summary:"
    echo "=================="
    echo "✅ Project directory: /var/www/kanbanflow-dashboard"
    echo "✅ Database: kanbanflow_dashboard"
    echo "✅ Database user: kanbanflow_user"
    echo "⚠️  TODO: Update .env file with production settings"
    echo "⚠️  TODO: Update Nginx config with your domain"
    echo "⚠️  TODO: Set up SSL with certbot for HTTPS"
    echo "⚠️  TODO: Configure queue workers if needed"
    echo ""
    echo "🎉 Initial setup complete!"
    echo ""
    echo "Next steps:"
    echo "1. Update /var/www/kanbanflow-dashboard/.env with production settings"
    echo "2. Update /etc/nginx/sites-available/kanbanflow-dashboard with your domain"
    echo "3. Run: sudo certbot --nginx -d YOUR_DOMAIN_HERE (for SSL)"
    echo "4. Set up supervisor for queue workers if using queues"
    
EOF

echo "✨ Server setup script completed!"
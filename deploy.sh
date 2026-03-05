#!/bin/bash

# Deploy script for KanbanFlow Dashboard
# Access at: http://kanbanflow.januki.ca

echo "🚀 Starting deployment to production..."

# Push to GitHub
echo "📤 Pushing to GitHub..."
git push origin main

# Deploy to production server
echo "🔄 Deploying to production server..."
ssh caws << 'EOF'
    cd /var/www/kanbanflow-dashboard
    echo "📥 Pulling latest changes..."
    sudo -u www-data git pull origin main

    echo "📦 Installing composer dependencies..."
    sudo -u www-data composer install --no-dev --optimize-autoloader

    echo "🏗️ Building frontend assets..."
    sudo -u www-data npm ci --production=false
    sudo -u www-data npm run build

    echo "🔧 Running Laravel optimizations..."
    sudo -u www-data php artisan config:cache
    sudo -u www-data php artisan route:cache
    sudo -u www-data php artisan view:cache
    sudo -u www-data php artisan event:cache

    echo "🗃️ Running migrations..."
    sudo -u www-data php artisan migrate --force

    echo "📂 Setting proper permissions..."
    sudo chown -R www-data:www-data storage bootstrap/cache
    sudo chmod -R 775 storage bootstrap/cache

    echo "🔄 Restarting services..."
    sudo systemctl reload php8.5-fpm
    sudo systemctl reload nginx

    # Restart queue workers if supervisor is installed
    command -v supervisorctl &> /dev/null && sudo supervisorctl restart all || echo "Supervisor not installed, skipping queue restart..."

    echo "✅ Deployment complete!"
EOF

echo "✨ Deployment finished successfully!"

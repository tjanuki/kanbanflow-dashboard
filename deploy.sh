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
    git pull origin main

    echo "📦 Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader

    echo "🏗️ Building frontend assets..."
    npm ci --production=false
    npm run build

    echo "🧹 Clearing stale caches..."
    php artisan optimize:clear
    php artisan filament:optimize-clear || true

    echo "🔧 Running Laravel optimizations..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    echo "🗃️ Running migrations..."
    php artisan migrate --force

    echo "📂 Setting proper permissions..."
    sudo chown -R ubuntu:www-data storage bootstrap/cache
    sudo chmod -R 775 storage bootstrap/cache

    echo "🔄 Restarting services..."
    sudo systemctl restart php8.5-fpm
    sudo systemctl reload nginx

    # Restart queue workers if supervisor is installed
    command -v supervisorctl &> /dev/null && sudo supervisorctl restart all || echo "Supervisor not installed, skipping queue restart..."

    echo "✅ Deployment complete!"
EOF

echo "✨ Deployment finished successfully!"

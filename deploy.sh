#! /bin/bash

set -euo pipefail

checkout(){
    echo "Checking out the repository..."

    sudo chown -R morleys:morleys ./

    echo "Pulling the latest code git..."
    #git reset --hard
    #git pull

    echo "Repository checkout completed!"
}

deploy(){
    echo "Composer install..."
    composer install 

    echo "Setting permissions for web server..."
    sudo chown -R www-data:www-data ./

    echo "Clearing and caching configuration..."
    sudo php artisan optimize
    sudo php artisan route:clear
    sudo php artisan config:clear

    echo "Deployment completed successfully!"    
}

checkout
deploy

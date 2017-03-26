#!/bin/bash
source app.cfg

# Re-map DB host
sudo sh -c "echo '\n127.0.0.1\t$DB_HOST $DB_HOST' >> /etc/hosts"

# Add DB User and load 
mysql -u root --password=blank -e "CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -u root --password=blank -e "GRANT ALL PRIVILEGES ON * . * TO '$DB_USER'@'localhost';"
mysql -u $DB_USER --password=$DB_PASS < db/init.sql

# Composer
cd ../src
composer install

echo '!!! App init complete, root password should now be reset to something secure'
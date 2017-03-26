#!/usr/bin/env bash
sudo apt-get update

### System level items
# Apache
sudo apt-get install -y apache2
# PHP
sudo apt-get install -y php
sudo apt-get install -y php-mysql
sudo apt-get install -y php-curl php-json php-cgi libapache2-mod-php
sudo apt-get install -y php-gd
# Python
sudo apt-get install -y python
# MySQL
sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password password blank'
sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password blank'
sudo apt-get install -y mysql-server
# Zip
sudo apt-get install -y zip


### Configure apache
sudo systemctl enable apache2
sudo rm -f /etc/apache2/sites-enabled/*
sudo cp /vagrant/deploy_resources/apache_site.conf /etc/apache2/sites-enabled/project.conf
sudo a2enmod rewrite
sudo systemctl start apache2

#### Web Tools
# Composer
curl -Ss https://getcomposer.org/installer | php
sudo mv composer.phar /usr/bin/composer
# PHPUnit
wget https://phar.phpunit.de/phpunit.phar
chmod +x phpunit.phar
sudo mv phpunit.phar /usr/local/bin/phpunit
# Node
sudo curl -sL https://deb.nodesource.com/setup_7.x | sudo -E bash -
sudo apt-get install -y nodejs
sudo apt-get install -y build-essential
sudo npm install npm -g
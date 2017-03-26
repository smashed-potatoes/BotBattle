#!/bin/bash
cd ../deploy_resources/db
./reload.sh
cd ../../tests
phpunit --bootstrap ../src/vendor/autoload.php .

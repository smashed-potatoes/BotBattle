#!/bin/bash
cd ../deploy_resources/db
./reload.sh
cd ../../tests
phpunit --verbose --bootstrap ../src/vendor/autoload.php .

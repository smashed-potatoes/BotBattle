source ../app.cfg
mysql -u $DB_USER --password=$DB_PASS < init.sql

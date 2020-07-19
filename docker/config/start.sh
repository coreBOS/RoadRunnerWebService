#!/usr/bin/env bash

DBNAME=$COREBOS_DATABASE
DBEXISTS=$(mysql -u $MYSQL_USER -p$MYSQL_ROOT_PASSWORD -h $MYSQL_HOST --batch --skip-column-names -e "SHOW DATABASES LIKE '"$DBNAME"';" | grep "$DBNAME" > /dev/null; echo "$?")
if [ $DBEXISTS -ne 0 -a -f "schema/$DBNAME.sql"]
then
	mysql -u $MYSQL_USER -p$MYSQL_ROOT_PASSWORD -h $MYSQL_HOST mysql -e "CREATE DATABASE $DBNAME DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;"
	mysql -u $MYSQL_USER -p$MYSQL_ROOT_PASSWORD -h $MYSQL_HOST $DBNAME < schema/$DBNAME.sql
fi
build/HelperScripts/createuserfiles
git checkout storage
chown -R www-data:www-data /var/www/html/storage
service cron start
service roadrunner start
exec /usr/sbin/apache2ctl -D FOREGROUND

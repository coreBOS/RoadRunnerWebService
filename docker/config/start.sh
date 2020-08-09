#!/usr/bin/env bash

MYSQLACTIVE=$(mysqladmin -u $MYSQL_USER -p$MYSQL_ROOT_PASSWORD -h $MYSQL_HOST status > /dev/null 2> /dev/null; echo "$?")
if [ $MYSQLACTIVE -ne 0 ]; then
	# have to wait > we leave with error and docker will restart the container to try again
	exit -1
fi

DBNAME=$COREBOS_DATABASE
DBEXISTS=$(mysql -u $MYSQL_USER -p$MYSQL_ROOT_PASSWORD -h $MYSQL_HOST --batch --skip-column-names -e "SHOW DATABASES LIKE '"$DBNAME"';" | grep "$DBNAME" > /dev/null; echo "$?")
if [ $DBEXISTS -ne 0 -a -f "schema/$DBNAME.sql"]
then
	mysql -u $MYSQL_USER -p$MYSQL_ROOT_PASSWORD -h $MYSQL_HOST mysql -e "CREATE DATABASE $DBNAME DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;"
	mysql -u $MYSQL_USER -p$MYSQL_ROOT_PASSWORD -h $MYSQL_HOST $DBNAME < schema/$DBNAME.sql
	build/HelperScripts/createuserfiles
	git checkout storage
fi
chown -R www-data:www-data /var/www/html/storage
service cron start
service roadrunner start
exec /usr/sbin/apache2ctl -D FOREGROUND

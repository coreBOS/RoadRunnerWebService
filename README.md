# coreBOS-RoadRunner Production Web Service

This project permits us to run a highly performant and stable coreBOS web service. The main goal is to have this running in a docker container accessing your customized coreBOS while your frontend code makes calls to the service, but it can also run alongside your normal access to the coreBOS application.

## Installation

- copy the files in the `corebos` directory into you coreBOS install
  - .rr.yaml you can adapt this file to your particular needs
  - include/Webservices/SessionManagerDB.php
  - webserviceload.php
  - webservicerr.php
- composer require spiral/roadrunner
- ./vendor/bin/rr get
- commit changes if you need to
- ./rr serve

In the `init.d` directory you can find a Unix service configuration file that you can copy to /etc/init.d and use the Linux `service` command to manage.

## Docker

In the `docker` directory you can find a docker file to create a coreBOS docker image with RoadRunner installed. The image will be an Ubuntu 20 with Apache, PHP 7.4 and your coreBOS copied and ready to start working in /var/www/html

- copy your coreBOS install with RoadRunner already copied and ready to be used into the `corebos` directory. You will have to have a correct config.inc.php file for the environment the docker container is going to run in.
- you will have to mount a local physical directory to `storage`
- optionally you can tweak the dockerfile to configure mounting the whole coreBOS install instead of just the storage directory
- the container must be called with these **environment variables** set:
  - $COREBOS_DATABASE
  - $MYSQL_USER
  - $MYSQL_ROOT_PASSWORD
  - $MYSQL_HOST
- copy a dump of your production database into the schema directory with the same name you have set in $COREBOS_DATABASE, the start script will look for a database with the name $COREBOS_DATABASE, if it is not found and a dump file names `schema/$COREBOS_DATABASE.sql` exists the database will be created and the dump loaded
- the cron system, roadrunner and apache will be started

## Notes

- Road Runner uses a different session and authentication management system than coreBOS does which makes the operation `extendsession` meaningless: `extendsession` is not supported with Road Runner

## Links

- [RoadRunner](https://roadrunner.dev/)
- [RoadRunner Configuration](https://roadrunner.dev/docs/intro-config)
- [RoadRunner CLI Commands](https://roadrunner.dev/docs/beep-beep-cli)
- [PHP Was Never Meant to Die](https://spiralscout.com/blog/php-was-never-meant-to-die)
- coreBOS-RoadRunner presentation (blog post coming soon)

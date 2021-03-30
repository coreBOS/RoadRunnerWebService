# coreBOS-RoadRunner Production Web Service

This project permits us to run a highly performant and stable coreBOS web service. The main goal is to have this running in a docker container accessing your customized coreBOS while your frontend code makes calls to the service, but it can also run alongside your normal access to the coreBOS application.

## Installation

- copy the files in the `corebos` directory into you coreBOS install
  - .rr.yaml you can adapt this file to your particular needs
  - include/Webservices/SessionManagerDB.php
  - webserviceload.php
  - webservicerr.php
- composer require spiral/roadrunner spiral/goridge spiral/roadrunner-metrics  nyholm/psr7 laminas/laminas-diactoros
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

## Login System Synchronization

Bundled with the Road Runner web service you will find also a new API endpoint named `loginSession`. Although not exclusively related to Road Runner as it could be used also with Apache, Nginx, or others with some tweaks in the session management, it permits us to distribute the login session identifier to other coreBOS installs. The use case is where we have a very large load of users accessing coreBOS web services and we want to distribute the workload among various coreBOS installs. All the installs must share the same code base and users. We can use any load balancer or routing system to distribute the calls to the different workers but the problem that appears is when a `login` is done in one of the systems, a session identifier is created and assigned for subsequent calls to send that session identifier and have access. In the case of various distributed coreBOS installs, the other installs do not recognize the session identifier. The loginSession endpoint will permit us to automatically send the assigned sessionid to the other systems so the balancer can send the request to any of the workers and get the expected result.

For this to work securely you must define the site URL of each synchronized coreBOS and set a shared private key for each one. The synchronization extension will detect a login on one of the systems and use the private key to authenticate against the other systems and establish the session identifier in each one. The logout action will also be distributed among the synchronized coreBOS installs.

coreBOS has a configuration section in Integrations where you can establish the synchronized URLs

**loginSession** accepts 4 parameters:

- username: user name to login with
- loggedinat: site URL of the originating system, this URL must be configured in the destination coreBOS
- hashaccess: the sha512 hash of the token and the shared private key
- sessionid: the session identifier to establish

Copy the two files into place and apply all in coreBOS updater

## Notes

- Road Runner uses a different session and authentication management system than coreBOS does which makes the operation `extendsession` meaningless: `extendsession` is not supported with Road Runner

## Links

- [RoadRunner](https://roadrunner.dev/)
- [RoadRunner Configuration](https://roadrunner.dev/docs/intro-config)
- [RoadRunner CLI Commands](https://roadrunner.dev/docs/beep-beep-cli)
- [PHP Was Never Meant to Die](https://spiralscout.com/blog/php-was-never-meant-to-die)
- coreBOS-RoadRunner presentation (blog post coming soon)

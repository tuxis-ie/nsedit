nsedit
======

DNS Editor working with PowerDNS' new API

You can testdrive it via http://nsedit.tuxis.nl/ . Log in with admin/admin.
Feel free to change stuff, but please let admin/admin be admin/admin :)


requirements
============
* A webserver running php
* php sqlite3
* php curl
* PowerDNS with the experimental JSON-api enabled

installing
==========

* Run 
```git clone --recursive https://github.com/tuxis-ie/nsedit.git```
where you want to run nsedit

* Copy ```includes/config.inc.php-dist``` to ```includes/config.inc.php``` and edit config.inc.php to your needs.

* Visit http(s)://<url>/nsedit/ and login with admin/admin (Don't forget to update your password!)

Have fun ;)


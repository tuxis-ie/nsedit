What is nsedit?
===============

nsedit is a DNS editor for [PowerDNS](https://www.powerdns.com/). It is created
to finally replace poweradmin and take DNS editing to what we're used at in
2014. It uses the PowerDNS API to make changes in your zones, so you can use
the backend that you want, no matter what.

Features
========
* Import BIND- or AXFR-style dumps of your existing zones
* Add/remove zones and records
* Show the DNSsec details of a zone
* Multiple user support 
* [experimental] nsedit API, to create zones from another system

User support
============
Multiple users are supported. A user can be an admin or a normal user. You can
configure wheter or not a normal user is allowed to add new zones.

WeFact Login support
====================
[WeFact](https://www.wefact.com/wefact-hosting/) is _A hosting billing
product_. nsedit can authenticate against the WeFact API, which allows your
customers to login on nsedit with their WeFact credentials. It will
automatically show their domains and allow them to edit them.

Requirements
============
* A webserver running php
* php sqlite3
* php curl
* PowerDNS with the experimental JSON-api enabled (3.4.0 should do)

Installing
==========

* Run 
```git clone --recursive https://github.com/tuxis-ie/nsedit.git```
where you want to run nsedit

* Copy ```includes/config.inc.php-dist``` to ```includes/config.inc.php``` and edit config.inc.php to your needs.

* Visit http(s)://<url>/nsedit/ and login with admin/admin (Don't forget to update your password!)

Have fun ;)

Screenshots
===========

![The login screen](screenshots/login.png)
![Master zones](screenshots/master-import-zones.png)
![Slave zones](screenshots/slavezones.png)



#!/usr/bin/env bash
[ -z "$PDNSAPIIP" ] && echo "Set PDNSAPIIP to your PowerDNS API IP/Hostname" && exit 1;
[ -z "$PDNSAPIPWD" ] && echo "Set PDNSAPIPWD to your PowerDNS API Password" && exit 1;

sed "s/\$apipass = ''/\$apipass = '$PDNSAPIPWD'/" -i /app/nsedit/includes/config.inc.php
sed "s/\$apiip   = ''/\$apiip = '$PDNSAPIIP'/" -i /app/nsedit/includes/config.inc.php
if [[ $PDNSAPIPORT && ${PDNSAPIPORT-x} ]]
then
    sed "s/\$apiport = '8081'/\$apiport = '$PDNSAPIPORT'/" -i /app/nsedit/includes/config.inc.php
fi
sed "s/\$authdb  = \"\.\.\/etc\/pdns\.users\.sqlite3\"/\$authdb  = \"\/app\/pdns\.users\.sqlite3\"/" -i /app/nsedit/includes/config.inc.php

exec /usr/bin/php -S 0.0.0.0:8080
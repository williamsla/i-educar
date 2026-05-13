#!/bin/sh
# Verifica se o PHP-FPM está a escutar em 127.0.0.1:9000 (usado pelo healthcheck do Docker).
php -r '$f=@fsockopen("127.0.0.1",9000,null,null,2); exit($f?(fclose($f)||0):1);'

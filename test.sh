#!/bin/bash
if [ ! -f phpunit.phar ]; then
  wget -O phpunit.phar https://phar.phpunit.de/phpunit-8.phar
fi
php phpunit.phar --bootstrap src/Loader.php src/Tests

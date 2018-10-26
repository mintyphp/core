#!/bin/bash
if [ ! -f phpunit.phar ]; then
  wget https://phar.phpunit.de/phpunit.phar
fi
php phpunit.phar --bootstrap src/Loader.php src/Tests

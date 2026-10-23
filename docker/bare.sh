#!/bin/sh
# The package without omniscient/omniauth and its methods (only suggested): a copy, the four removed,
# the group without-omniauth run there.
set -e
mkdir -p /tmp/bare
cp -r /source/src /source/tests /source/config /source/composer.json /source/phpunit.xml.dist /tmp/bare/
cd /tmp/bare
composer remove --dev --no-update omniscient/omniauth omniauth/google omniauth/webauthn omniauth/otp
composer update --no-progress -q
php -r 'exit(class_exists("Omniauth\\Omniauth") ? 1 : 0);'
vendor/bin/phpunit --group without-omniauth

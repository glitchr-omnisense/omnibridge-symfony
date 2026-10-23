#!/bin/sh
# The package without omniscient/omniauth and its methods, omniscient/omnimap and its providers, nor omniscient/omnidrive
# (only suggested): a copy, those removed, the group without-omniauth run there.
set -e
mkdir -p /tmp/bare
cp -r /source/src /source/tests /source/config /source/composer.json /source/phpunit.xml.dist /tmp/bare/
cd /tmp/bare
composer remove --dev --no-update omniscient/omniauth omniauth/google omniauth/webauthn omniauth/otp omniscient/omnimap omnimap/google omnimap/openstreetmap omniscient/omnidrive league/flysystem-memory
composer update --no-progress -q
php -r 'exit(class_exists("Omniauth\\Omniauth") || class_exists("Omnimap\\Registry") || class_exists("Omnidrive\\Registry") ? 1 : 0);'
vendor/bin/phpunit --group without-omniauth

#!/usr/bin/env bash

docker rm -f zilch-wp-install-script

docker build -t zilch-wp-install-script:php-test -f scripts/docker/php-test.Dockerfile .
docker run --name zilch-wp-install-script zilch-wp-install-script:php-test /bin/sh -c \
  "composer test && composer lint && composer build"
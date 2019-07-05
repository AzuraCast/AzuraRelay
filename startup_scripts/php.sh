#!/bin/bash

# Copy the php.ini template to its destination.
dockerize -template "/etc/php/7.2/fpm/05-azuracast.ini.tmpl:/etc/php/7.2/fpm/conf.d/05-azuracast.ini" /bin/true
#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

$minimal_apt_get_install supervisor

cp /bd_build/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
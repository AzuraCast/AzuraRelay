#!/bin/bash

# Touch the crontab links to fix a Docker-specific hardlink issue.
touch /etc/crontab /etc/cron.*/*
.PHONY: *

list:
	@LC_ALL=C $(MAKE) -pRrq -f $(lastword $(MAKEFILE_LIST)) : 2>/dev/null | awk -v RS= -F: '/^# File/,/^# Finished Make data base/ {if ($$1 !~ "^[#.]") {print $$1}}' | sort | egrep -v -e '^[^[:alnum:]]' -e '^$@$$'

up:
	docker-compose up -d

down:
	docker-compose down

restart: down up

build: # Rebuild all containers and restart
	docker-compose build
	$(MAKE) restart

install:
	cp docker-compose.sample.yml docker-compose.yml
	cp docker-compose.dev.yml docker-compose.override.yml
	touch azurarelay.env

	docker-compose build

bash:
	docker-compose exec --user=app relay bash

bash-root:
	docker-compose exec relay bash

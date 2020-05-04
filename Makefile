current_user  := $(shell id -u)
current_group := $(shell id -g)
BUILD_DIR     := $(PWD)
TMPDIR        := $(BUILD_DIR)/tmp
COMPOSER_FLAGS :=
NPM_FLAGS     := --prefer-offline
DOCKER_FLAGS  := --interactive --tty
TEST_DIR      :=
MIGRATION_VERSION :=
MIGRATION_CONTEXT :=
APP_ENV       := dev
DOCKER_IMAGE  := wikimediade/fundraising-frontend

NODE_IMAGE    := node:12
DOCKER_IMAGE  := wikimediade/fundraising-frontend


.DEFAULT_GOAL := ci

up_app: down_app
	docker-compose -f docker-compose.yml up -d

up_debug: down_app
	docker-compose -f docker-compose.yml -f docker-compose.debug.yml up -d

down_app:
	docker-compose -f docker-compose.yml -f docker-compose.debug.yml down > /dev/null 2>&1

install-js:
	-mkdir -p $(TMPDIR)/home
	-echo "node:x:$(current_user):$(current_group)::/var/nodehome:/bin/bash" > $(TMPDIR)/passwd
	docker run --rm $(DOCKER_FLAGS) --user $(current_user):$(current_group) -v $(BUILD_DIR):/data:delegated -w /data -v $(TMPDIR)/home:/var/nodehome:delegated -v $(TMPDIR)/passwd:/etc/passwd $(NODE_IMAGE) npm install $(NPM_FLAGS)

install-php:
	docker run --rm $(DOCKER_FLAGS) --volume $(BUILD_DIR):/app -w /app --volume /tmp:/tmp --volume ~/.composer:/composer --user $(current_user):$(current_group) $(DOCKER_IMAGE):composer composer install $(COMPOSER_FLAGS)

update-js:
	-mkdir -p $(TMPDIR)/home
	-echo "node:x:$(current_user):$(current_group)::/var/nodehome:/bin/bash" > $(TMPDIR)/passwd
	docker run --rm $(DOCKER_FLAGS) --user $(current_user):$(current_group) -v $(BUILD_DIR):/data:delegated -v $(TMPDIR)/home:/var/nodehome:delegated -v $(TMPDIR)/passwd:/etc/passwd $(NODE_IMAGE) npm update $(NPM_FLAGS)

update-php:
	docker run --rm $(DOCKER_FLAGS) --volume $(BUILD_DIR):/app -w /app --volume ~/.composer:/composer --user $(current_user):$(current_group) $(DOCKER_IMAGE):composer composer update $(COMPOSER_FLAGS)

default-config:
	cp -i build/app/config.dev.json app/config

js:
	docker run --rm $(DOCKER_FLAGS) --user $(current_user):$(current_group) -v $(BUILD_DIR):/data:delegated -w /data/skins/laika -e NO_UPDATE_NOTIFIER=1 $(NODE_IMAGE) npm run build

clear:
	rm -rf var/cache/
	docker-compose run --rm --no-deps app rm -rf var/cache/

# n alias to avoid frequent typo
clean: clear

ui: clear js

test: covers phpunit

setup-db:
	docker-compose run --rm start_dependencies
	docker-compose run --rm app ./vendor/bin/doctrine orm:schema-tool:create
	docker-compose run --rm app ./vendor/bin/doctrine orm:generate-proxies var/doctrine_proxies

covers:
	docker-compose run --rm --no-deps app ./vendor/bin/covers-validator

phpunit:
	docker-compose run --rm app ./vendor/bin/phpunit $(TEST_DIR)

phpunit-with-coverage:
	docker-compose -f docker-compose.yml -f docker-compose.debug.yml run --rm app_debug ./vendor/bin/phpunit --dump-xdebug-filter var/xdebug-filter.php
	docker-compose -f docker-compose.yml -f docker-compose.debug.yml run --rm app_debug ./vendor/bin/phpunit --prepend var/xdebug-filter.php --configuration=phpunit.xml.dist --stop-on-error --coverage-clover coverage.clover --printer="PHPUnit\TextUI\ResultPrinter"

phpunit-system:
	docker-compose run --rm app ./vendor/bin/phpunit tests/System/

cs:
	docker-compose run --rm --no-deps app ./vendor/bin/phpcs

fix-cs:
	docker-compose run --rm --no-deps app ./vendor/bin/phpcbf

stan:
	docker run --rm -it --volume $(BUILD_DIR):/app -w /app $(DOCKER_IMAGE):stan analyse --level=1 --no-progress cli/ src/ tests/

validate-app-config:
	docker-compose run --rm --no-deps app ./console app:validate:config app/config/config.dist.json app/config/config.test.json

validate-campaign-config:
	docker-compose run --rm --no-deps app ./console app:validate:campaigns $(APP_ENV)

validate-campaign-utilization:
	docker-compose run --rm --no-deps app ./console app:validate:campaigns:utilization

phpmd:
	docker-compose run --rm --no-deps app ./vendor/bin/phpmd src/ text phpmd.xml

npm-ci:
	docker run --rm $(DOCKER_FLAGS) --user $(current_user):$(current_group) -v $(BUILD_DIR):/code -w /code -e NO_UPDATE_NOTIFIER=1 $(NODE_IMAGE) npm run ci

migration-execute:
	@test $(MIGRATION_CONTEXT) || ( echo "MIGRATION_CONTEXT must be set!" && exit 1)
	# TODO unify all migrations when migrations 3.0 (with migrations_path instead of migration_dir) is available
	docker-compose run --rm --no-deps app vendor/doctrine/migrations/bin/doctrine-migrations migrations:execute $(MIGRATION_VERSION) --up --configuration=app/config/migrations/$(MIGRATION_CONTEXT).yml

migration-revert:
	@test $(MIGRATION_CONTEXT) || ( echo "MIGRATION_CONTEXT must be set!" && exit 1)
	docker-compose run --rm --no-deps app vendor/doctrine/migrations/bin/doctrine-migrations migrations:execute $(MIGRATION_VERSION) --down --configuration=app/config/migrations/$(MIGRATION_CONTEXT).yml

migration-status:
	# TODO provide more migrations configurations when available.
	docker-compose run --rm --no-deps app vendor/doctrine/migrations/bin/doctrine-migrations migrations:status --configuration=app/config/migrations/subscriptions.yml

ci: covers phpunit cs npm-ci validate-app-config validate-campaign-config stan

ci-with-coverage: covers phpunit-with-coverage cs npm-ci validate-app-config validate-campaign-config stan

create-env: 
	if [ ! -f .env ]; then echo "APP_ENV=dev">.env; fi

setup: create-env install-php install-js default-config ui setup-db

.PHONY: ci ci-with-coverage clean clear covers cs install-php install-js js npm-ci npm-install phpmd phpunit phpunit-system setup stan test ui validate-app-config validate-campaign-config

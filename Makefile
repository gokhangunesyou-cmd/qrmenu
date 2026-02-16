.PHONY: help up down build shell db migrate seed seed-app test lint fix install diff reset import-catalog

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

build: ## Build containers
	docker compose build --no-cache

shell: ## Open PHP container shell
	docker compose exec php sh

db: ## Create database
	docker compose exec php php bin/console doctrine:database:create --if-not-exists

migrate: ## Run migrations
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

seed: ## Load seed data (fixtures)
	docker compose exec php php bin/console doctrine:fixtures:load --append --no-interaction

seed-app: ## Load seed data via app seed command
	docker compose exec php php bin/console app:fixtures:seed

test: ## Run test suite
	docker compose exec php php bin/phpunit

lint: ## Run PHPStan static analysis
	docker compose exec php vendor/bin/phpstan analyse

fix: ## Fix code style
	docker compose exec php vendor/bin/php-cs-fixer fix

install: ## Install composer dependencies
	docker compose exec php composer install

diff: ## Generate migration diff
	docker compose exec php php bin/console doctrine:migrations:diff

reset: ## Reset database (drop + create + migrate + seed)
	docker compose exec php php bin/console doctrine:database:drop --force --if-exists
	docker compose exec php php bin/console doctrine:database:create
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec php php bin/console doctrine:fixtures:load --append --no-interaction

import-catalog: ## Import product catalog from JSON (use SLUG=... JSON=...)
	@test -n "$(SLUG)" || (echo "SLUG is required. Example: make import-catalog SLUG=gunesli-bahce-yoresel-lezzetleri JSON=var/gunesli_bahce_products_images.json" && exit 1)
	@test -n "$(JSON)" || (echo "JSON is required. Example: make import-catalog SLUG=gunesli-bahce-yoresel-lezzetleri JSON=var/gunesli_bahce_products_images.json" && exit 1)
	docker compose exec php php bin/console app:catalog:import-json "$(SLUG)" "$(JSON)"

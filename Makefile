.PHONY: help up down build shell db migrate seed test lint fix

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

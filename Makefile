.PHONY: dev stop restart rebuild logs shell test test-unit test-integration test-golden test-property test-coverage lint fix stan infection deptrac ci migrate migrate-diff consume deploy composer-install fresh status

# === Development ===
dev:
	docker compose up -d

stop:
	docker compose down

restart:
	docker compose restart

rebuild:
	docker compose build --no-cache
	docker compose up -d

fresh: stop
	docker compose up -d --build
	$(MAKE) composer-install
	$(MAKE) migrate

logs:
	docker compose logs -f app

logs-worker:
	docker compose logs -f messenger-worker

shell:
	docker compose exec app sh

status:
	docker compose ps

composer-install:
	docker compose exec app composer install --no-interaction

composer-update:
	docker compose exec app composer update --no-interaction

# === Testing (TDD) ===
test:
	docker compose exec app php vendor/bin/phpunit

test-unit:
	docker compose exec app php vendor/bin/phpunit --testsuite=unit

test-integration:
	docker compose exec app php vendor/bin/phpunit --testsuite=integration

test-golden:
	docker compose exec app php vendor/bin/phpunit --testsuite=golden-dataset

test-property:
	docker compose exec app php vendor/bin/phpunit --testsuite=property

test-coverage:
	docker compose exec app php vendor/bin/phpunit --coverage-html var/coverage

# === Quality ===
lint:
	docker compose exec app vendor/bin/ecs check

fix:
	docker compose exec app vendor/bin/ecs check --fix

stan:
	docker compose exec app php vendor/bin/phpstan analyse --level=max

infection:
	docker compose exec app php vendor/bin/infection --min-msi=80 --threads=4

deptrac:
	docker compose exec app php vendor/bin/deptrac

# === All checks (CI parity) ===
ci: lint stan test deptrac

# === Database ===
migrate:
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff:
	docker compose exec app php bin/console doctrine:migrations:diff

# === Messenger ===
consume:
	docker compose exec app php bin/console messenger:consume async -vv

failed:
	docker compose exec app php bin/console messenger:failed:show

retry-failed:
	docker compose exec app php bin/console messenger:failed:retry

# === Deploy (MyDevil) ===
deploy:
	vendor/bin/dep deploy

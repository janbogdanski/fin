.PHONY: dev stop logs shell test test-unit test-integration test-golden test-property lint fix stan infection ci migrate deploy

# === Development ===
dev:
	docker compose up -d

stop:
	docker compose down

logs:
	docker compose logs -f app

shell:
	docker compose exec app sh

composer-install:
	docker compose exec app composer install

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
ci: lint stan test

# === Database ===
migrate:
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff:
	docker compose exec app php bin/console doctrine:migrations:diff

# === Messenger ===
consume:
	docker compose exec app php bin/console messenger:consume async -vv

# === Deploy (MyDevil) ===
deploy:
	vendor/bin/dep deploy

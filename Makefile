.PHONY: dev stop restart rebuild logs shell test test-unit test-integration test-golden test-property test-contract test-coverage lint fix stan infection deptrac ci migrate migrate-diff consume deploy composer-install fresh status pact-broker pact-publish pact-verify

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

test-contract:
	docker compose exec app php vendor/bin/phpunit --testsuite=contract

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

# === Pact Contract Testing ===
pact-broker:
	@echo "Opening Pact Broker at http://localhost:9292"
	@open http://localhost:9292 2>/dev/null || xdg-open http://localhost:9292 2>/dev/null || echo "Visit http://localhost:9292"

pact-publish:
	@echo "Publishing pacts to local broker..."
	docker compose exec app php vendor/bin/pact-stub-server --help >/dev/null 2>&1 || true
	@curl -s -X PUT \
		-H "Content-Type: application/json" \
		-d @tests/pacts/TaxPilot-NBP_API.json \
		"http://localhost:9292/pacts/provider/NBP_API/consumer/TaxPilot/version/$$(date +%Y%m%d%H%M%S)" \
		&& echo "Pact published successfully" \
		|| echo "Failed to publish pact. Is the broker running? (make dev)"

pact-verify:
	@echo "Verifying provider contracts against broker..."
	@curl -s "http://localhost:9292/pacts/provider/NBP_API/latest" > /dev/null \
		&& echo "Provider pact found in broker" \
		|| echo "No pacts found for NBP_API provider. Run 'make test-contract && make pact-publish' first."

# === All checks (CI parity) ===
ci: lint stan test deptrac test-contract

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

.PHONY: dev stop restart rebuild logs shell test test-unit test-integration test-golden test-property test-contract test-canary test-coverage lint fix stan infection deptrac ci migrate migrate-diff consume deploy composer-install fresh status pact pact-broker pact-publish pact-verify tailwind-build tailwind-watch load-test-landing load-test-import load-test-spike seed login-link

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
	$(MAKE) tailwind-build
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

test-canary:
	docker compose exec app php vendor/bin/phpunit --testsuite=canary --group=canary

test-chaos:
	docker compose exec app php vendor/bin/phpunit --testsuite=chaos --group=chaos

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
pact: test-contract pact-publish pact-verify
	@echo "Pact pipeline complete: tests → publish → verify"

pact-broker:
	@echo "Opening Pact Broker at http://localhost:9292"
	@open http://localhost:9292 2>/dev/null || xdg-open http://localhost:9292 2>/dev/null || echo "Visit http://localhost:9292"

pact-publish:
	@echo "Publishing pacts to local broker..."
	@curl -s -X PUT \
		-u pact:pact \
		-H "Content-Type: application/json" \
		-d @tests/pacts/TaxPilot-NBP_API.json \
		"http://localhost:9292/pacts/provider/NBP_API/consumer/TaxPilot/version/$$(date +%Y%m%d%H%M%S)" \
		&& echo "Pact published successfully" \
		|| echo "Failed to publish pact. Is the broker running? (make dev)"

pact-verify:
	@echo "Verifying provider contracts against broker..."
	@curl -s -u pact:pact "http://localhost:9292/pacts/provider/NBP_API/latest" > /dev/null \
		&& echo "Provider pact found in broker" \
		|| echo "No pacts found for NBP_API provider. Run 'make test-contract && make pact-publish' first."

# === All checks (CI parity) ===
ci: lint stan test deptrac test-contract

# === Dev seed & login ===
seed:
	docker compose exec -T postgres psql -U app -d app < docker/seed.sql
	@echo "Seed done. Run: make login-link"

login-link:
	@echo "Usage: make login-link [email=user@example.com]  (default: dev@taxpilot.local)"
	docker compose exec app php bin/console app:dev:login-link $(email)

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

# === Assets ===
tailwind-build:
	docker compose exec app tailwindcss -i assets/styles/app.css -o public/assets/styles/app.css --minify

tailwind-watch:
	docker compose exec app tailwindcss -i assets/styles/app.css -o public/assets/styles/app.css --watch

# === Load Testing (k6) ===
load-test-landing:
	@mkdir -p var/load-results
	docker compose --profile load-test run --rm k6 run /scripts/landing.js

load-test-import:
	@mkdir -p var/load-results
	docker compose --profile load-test run --rm k6 run /scripts/import-flow.js

load-test-spike:
	@mkdir -p var/load-results
	docker compose --profile load-test run --rm k6 run /scripts/pit-season.js

# === Deploy (MyDevil) ===
deploy:
	vendor/bin/dep deploy

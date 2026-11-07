# DevFest Lab helpers
#
#   make up       build + start both apps (http://localhost:8080, :8081)
#   make down     stop the stack
#   make reset    wipe the database and rebuild everything
#   make logs     follow container logs
#   make test     run the end-to-end smoke test against both apps

.PHONY: up down reset logs test

up:
	docker compose up -d --build

down:
	docker compose down

reset:
	docker compose down -v --remove-orphans
	docker compose up -d --build

logs:
	docker compose logs -f

test:
	./scripts/smoke-test.sh

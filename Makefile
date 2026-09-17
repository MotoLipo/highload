.DEFAULT_GOAL := help

.PHONY: help setup up down restart build migrate logs test stan lint lint-fix check traffic k8s-up k8s-down clean

help: ## Показать список команд
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-14s\033[0m %s\n", $$1, $$2}'

setup: ## Первый запуск целиком: .env, сборка образа, ключ, docker compose up, миграции (см. README "Запуск (локально)")
	[ -f .env ] || cp .env.example .env
	docker compose build
	docker compose run --rm app php artisan key:generate
	docker compose up -d
	docker compose exec app php artisan clickhouse:migrate
	docker compose exec app php artisan migrate --seed

up: ## Поднять весь стек (docker compose up -d)
	docker compose up -d

down: ## Остановить стек
	docker compose down

restart: down up ## Перезапустить стек (down + up)

build: ## Пересобрать образ app (после изменений в Dockerfile/composer.*)
	docker compose build app

migrate: ## Прогнать миграции ClickHouse + SQLite (стек должен быть уже поднят)
	docker compose exec app php artisan clickhouse:migrate
	docker compose exec app php artisan migrate --seed

logs: ## Логи всех сервисов (Ctrl+C для выхода). Один сервис: make logs service=app
	docker compose logs -f $(service)

test: ## Тесты (локально, PHP 8.3+/Composer - не в докере, см. README "Тестирование")
	composer run test

stan: ## Статический анализ (PHPStan/Larastan level 8)
	composer run stan

lint: ## Проверить стиль кода по всему проекту (Pint, без изменений)
	./vendor/bin/pint --test

lint-fix: ## Поправить стиль кода по всему проекту (Pint) - см. предупреждение выше про lint
	./vendor/bin/pint

check: test stan ## Все проверки из CI разом (deploy.yml, джоба test: test + stan + audit)
	composer audit

traffic: ## Накидать тестовых событий в /api/events (чтобы увидеть данные в Grafana/Kibana)
	for i in $$(seq 1 20); do \
		curl -s -o /dev/null -X POST http://localhost:8080/api/events \
			-H "Content-Type: application/json" \
			-d '{"event_type":"page_view","user_id":1,"metadata":{}}'; \
	done
	@echo "20 событий отправлено в /api/events"

k8s-up: ## Локальный kind-кластер: namespace, секрет, манифесты (см. README "Запуск (Kubernetes)")
	kind create cluster
	kubectl apply -f k8s/namespace.yaml
	kubectl create secret generic analytics-secret \
		--from-literal=APP_KEY="$$(php artisan key:generate --show)" \
		-n analytics
	kubectl apply -f k8s/

k8s-down: ## Удалить локальный kind-кластер
	kind delete cluster

clean: ## Остановить стек и стереть volumes (ClickHouse/Elasticsearch данные удаляются)
	docker compose down -v

SHELL := /bin/bash

.PHONY: help backend-env frontend-env backend-install frontend-install setup backend-test frontend-lint frontend-build check

help:
	@printf '%s\n' \
		'Available targets:' \
		'  setup          Install dependencies and prepare local env files' \
		'  backend-test   Run the Laravel test suite' \
		'  frontend-lint  Run frontend lint checks' \
		'  frontend-build Run a production frontend build' \
		'  check          Run the default validation set used by CI'

backend-env:
	@if [ ! -f backend/.env ]; then cp backend/.env.example backend/.env; fi
	@mkdir -p backend/database
	@if [ ! -f backend/database/database.sqlite ]; then touch backend/database/database.sqlite; fi

frontend-env:
	@if [ ! -f frontend/.env.local ]; then cp frontend/.env.example frontend/.env.local; fi

backend-install: backend-env
	cd backend && composer install --no-interaction --prefer-dist
	cd backend && php artisan key:generate

frontend-install: frontend-env
	cd frontend && npm install

setup: backend-install frontend-install
	cd backend && php artisan migrate --seed

backend-test: backend-install
	cd backend && php artisan test

frontend-lint: frontend-install
	cd frontend && npm run lint

frontend-build: frontend-install
	cd frontend && npm run build

check: backend-test frontend-lint frontend-build

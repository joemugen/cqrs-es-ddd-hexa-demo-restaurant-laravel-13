# Run the stack from one terminal, one prefixed log stream per process. Ctrl+C stops everything.
# `composer dev`, `composer dev:sync` and `composer workers` call these targets.
#
# Each process is a target and `make -j` runs them side by side. A recipe replaces its shell with the process
# (exec), so the signals make sends reach it directly and nothing outlives make. If one process stops, a worker
# refused by its lock for instance, the others keep running: read the error in its stream and press Ctrl+C.

SHELL := bash
.DEFAULT_GOAL := help

PHP ?= $(or $(PHP_BINARY),php)
HOST ?= 127.0.0.1
PORT ?= 8000

# The workers are the `*:consume` commands artisan lists, so a new bounded context needs no change here.
CONTEXTS = $(shell $(PHP) artisan list --raw | awk '/:consume( |$$)/ { split($$1, name, ":"); print name[1] }')
WORKERS = $(addprefix consume-,$(CONTEXTS))

# spawn <name> <command>: run the command with every line of its output prefixed by [name].
define spawn
@exec > >(sed -u 's/^/[$(1)] /') 2>&1; exec $(2)
endef

.PHONY: help dev dev-sync workers prepare serve queue logs

help: ## List the targets
	@awk -F':.*## ' '/^[a-z-]+:.*## / { printf "  make %-10s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

dev: prepare ## The API and one worker per context (worker mode)
	@EVENT_CONSUMPTION=worker exec $(MAKE) --no-print-directory -j serve $(WORKERS)

dev-sync: prepare ## The API and the queue worker, consumers run in the request (sync mode)
	@EVENT_CONSUMPTION=sync exec $(MAKE) --no-print-directory -j serve queue

workers: prepare ## Only the workers, when the API runs elsewhere
	@EVENT_CONSUMPTION=worker exec $(MAKE) --no-print-directory -j $(WORKERS)

prepare: .env database/database.sqlite ## Create .env and the SQLite file if missing, then migrate
	@$(PHP) artisan migrate --force

.env:
	cp .env.example .env
	$(PHP) artisan key:generate --force

database/database.sqlite:
	mkdir -p database
	touch $@

serve: ## php artisan serve on HOST:PORT (127.0.0.1:8000)
	$(call spawn,serve,$(PHP) artisan serve --host=$(HOST) --port=$(PORT))

queue: ## php artisan queue:listen
	$(call spawn,queue,$(PHP) artisan queue:listen --tries=1 --timeout=0)

logs: ## php artisan pail, to tail the application log in a second terminal (stop it with Ctrl+C)
	$(PHP) artisan pail --timeout=0

consume-%: ## make consume-kitchen: one worker for a context
	$(call spawn,$*,$(PHP) artisan $*:consume)

.PHONY: check lint validate test help

check: lint validate test  ## Все проверки (lint + validate + test)

lint:  ## Синтаксический контроль всех PHP-файлов
	@set -e; \
	for f in bin/* src/*.php src/TodoMd/*.php tests/*.php tests/Support/*.php; do \
		[ -f "$$f" ] && php -l "$$f"; \
	done

validate:  ## Валидация задач и эпиков пакета
	php bin/todo-md validate .

test:  ## Запуск тестов
	php tests/run.php

help:  ## Показать список целей
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

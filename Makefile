.PHONY: install serve test lint format analyse security-audit syntax check ci

install:
	composer install

serve:
	composer serve

test:
	composer test

lint:
	composer lint

format:
	composer format

analyse:
	composer analyse

security-audit:
	composer security:audit

syntax:
	composer syntax

check:
	composer check

ci:
	composer ci

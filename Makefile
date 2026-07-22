.PHONY: install hooks-install serve test lint format analyse security-audit syntax check ci dependencies-verify deploy-preflight

install:
	composer install

hooks-install:
	composer hooks:install

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

dependencies-verify:
	composer dependencies:verify

deploy-preflight:
	composer deploy:preflight

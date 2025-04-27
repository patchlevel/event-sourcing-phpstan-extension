help:                                                                           ## shows this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

vendor: composer.lock
	composer install

vendor-tools: tools/composer.lock
	cd tools && composer install

.PHONY: cs-check
cs-check: vendor                                                                ## run phpcs
	vendor/bin/phpcs

.PHONY: cs
cs: vendor                                                                      ## run phpcs fixer
	vendor/bin/phpcbf || true
	vendor/bin/phpcs

.PHONY: phpstan
phpstan: vendor                                                                 ## run phpstan static code analyser
	php vendor/bin/phpstan

.PHONY: phpstan-baseline
phpstan-baseline: vendor                                                        ## run phpstan static code analyser
	php vendor/bin/phpstan --generate-baseline

.PHONY: static
static: phpstan cs                                              			 	## run static analyser

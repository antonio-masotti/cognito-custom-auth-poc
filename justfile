

php-cs-fix:
    PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix

php-cs-check:
    PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer check

phpstan:
    vendor/bin/phpstan analyse

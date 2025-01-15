

php-cs-fix:
    PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix

php-cs-check:
    PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer check

phpstan:
    vendor/bin/phpstan analyse

start:
    symfony server:start

stop:
    symfony server:stop

start-docker:
    docker-compose up -d --build

stop-docker:
    docker-compose down

docker-prune:
    docker system prune -a

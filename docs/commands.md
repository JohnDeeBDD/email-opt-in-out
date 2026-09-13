# Setup

cd /var/www/html/test-harness && php test-harness.php --action=destroy --target=/var/www/html/wp-content/plugins/aiplugin5055 --force && php test-harness.php --action=bootstrap --no-cache --force --target=/var/www/html/wp-content/plugins/aiplugin5055 && cd /var/www/html/wp-content/plugins/aiplugin5055 && make build && make up && sleep 20 && cp /var/www/html/wp-content/plugins/aiplugin5055/tests/aiplugin5055.wpunit.yml /var/www/html/wp-content/plugins/aiplugin5055/tests/wpunit.suite.yml && cp /var/www/html/wp-content/plugins/aiplugin5055/tests/aiplugin5055.StartupCept.php /var/www/html/wp-content/plugins/aiplugin5055/tests/StartupCept.php && docker compose exec wordpress bash -c "cd /var/project && bash .devenv/start-chromedriver.sh && bin/codecept run acceptance /tests/StartupCept.php -vvv"


## Run a test from outside the container

docker compose exec wordpress bash -c "cd /var/project && bash .devenv/start-chromedriver.sh && bin/codecept run acceptance PluginInfoSCBugCept -vvv --html"

docker compose exec wordpress bash -c "cd /var/project && bin/codecept run wpunit SomeTest -vvv --html"

## Clean and Build 
docker compose exec wordpress bash -c "cd /var/project && bash .devenv/start-chromedriver.sh && bin/codecept clean" && docker compose exec wordpress bash -c "cd /var/project && bash .devenv/start-chromedriver.sh && bin/codecept build"

## run a test from inside the container
make shell
 - then -
cd /var/www/html/wp-content/plugins/aiplugin5055 && .devenv/start-chromedriver.sh && bin/codecept run acceptance CenteredItemsCepts -vvv --html

php /var/www/html/ai-plugin-dev-system-actions/src/doMakeProd.php --config=/var/www/html/ai-plugin-dev-cli/ai-plugin-dev-cli-config.json --version=9
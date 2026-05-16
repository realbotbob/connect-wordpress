# Contributing

Keep changes small, explicit, and covered by tests. Security-sensitive behavior
belongs in focused services rather than controller code.

Before opening a pull request, run:

```bash
composer validate --strict
composer install --no-interaction
composer test
composer lint
composer analyse
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
git diff --check
```

Do not commit generated release ZIPs or `vendor/`.


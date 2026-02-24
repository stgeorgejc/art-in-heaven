# Contributing to Art in Heaven

## Getting Started

```bash
git clone git@github.com:stgeorgejc/art-in-heaven.git
cd art-in-heaven
composer install
composer setup-hooks
```

The `setup-hooks` command configures git to use the `.githooks/` directory, which runs automated checks before every commit.

## Commit Messages

This project uses [Conventional Commits](https://www.conventionalcommits.org/). The `commit-msg` hook enforces this automatically.

### Format

```
type(optional-scope): description

optional body

optional footer
```

### Types

| Type | When to use |
|------|-------------|
| `feat` | New feature or capability |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `chore` | Maintenance (deps, config, CI) |
| `refactor` | Code change that doesn't fix a bug or add a feature |
| `test` | Adding or updating tests |
| `style` | Formatting, whitespace, semicolons |
| `perf` | Performance improvement |
| `ci` | CI/CD workflow changes |
| `build` | Build system or tooling changes |
| `revert` | Reverting a previous commit |

### Examples

```
feat: add web push notifications for outbid users
fix(auth): handle expired confirmation codes gracefully
docs: update integration guide with Pushpay sandbox steps
chore: bump PHPUnit to v12
refactor(cache): switch to version-counter group invalidation
test: add unit tests for AIH_Security sanitize methods
```

## Branch Naming

Use prefixes that match the commit types:

```
feat/add-push-notifications
fix/expired-confirmation-codes
docs/update-admin-guide
chore/bump-dependencies
```

These prefixes trigger automatic PR labeling via release-drafter, which feeds into auto-generated release notes.

## Testing

### Running Tests

```bash
composer test                  # PHPUnit (64+ unit tests)
composer analyse               # PHPStan static analysis
vendor/bin/phpunit --filter SecurityTest   # run a specific test class
```

### Writing Tests

Tests live in `tests/Unit/` and use [PHPUnit](https://phpunit.de/) with [Brain Monkey](https://github.com/Brain-WP/BrainMonkey) for WordPress function mocking.

```php
<?php
namespace ArtInHeaven\Tests\Unit;

use AIH_Security;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Stub WordPress functions your code calls
        Functions\stubs([
            'sanitize_text_field' => function ($v) { return trim(strip_tags($v)); },
            '__' => function ($text) { return $text; },
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
```

Good candidates for new tests:
- Pure logic methods (validation, formatting, computation)
- Methods in `AIH_Security`, `AIH_Status`, `AIH_Template_Helper`
- Any new business logic you add

### Static Analysis

PHPStan runs at level 5 with WordPress stubs. If you add a new class file, it will be automatically included in analysis (anything under `includes/` or `admin/class-aih-admin.php`).

To check a specific file:

```bash
vendor/bin/phpstan analyse includes/class-aih-your-file.php --memory-limit=1G
```

## Pre-commit Checks

The pre-commit hook runs automatically on every commit with staged PHP files:

1. **PHP lint** — syntax check on staged files
2. **PHPUnit** — full test suite
3. **PHPStan** — static analysis

If any check fails, the commit is blocked. Fix the issue and try again.

To skip hooks temporarily (not recommended):

```bash
git commit --no-verify -m "feat: emergency fix"
```

## Pull Request Process

1. Create a branch from `main` using the naming conventions above.
2. Make your changes with conventional commit messages.
3. Push and open a PR against `main`.
4. CI runs three parallel checks: lint, test, phpstan. All must pass.
5. PR is reviewed and merged.
6. On merge, the release workflow auto-drafts release notes and deploys.

## Project Structure

| Directory | Contents |
|-----------|----------|
| `includes/` | Core PHP classes (models, API clients, helpers) |
| `admin/` | Admin panel handler and view templates |
| `templates/` | Frontend page templates and partials |
| `assets/` | CSS, JS, fonts, and images |
| `tests/` | PHPUnit test suite |
| `.githooks/` | Git hook scripts |
| `.github/` | CI workflows, release-drafter config, Dependabot |

## Dependencies

**Runtime** (installed in production):
- `minishlink/web-push` — Web Push Protocol for browser notifications

**Dev** (testing and analysis):
- `phpunit/phpunit` — Test runner
- `brain/monkey` — WordPress function mocking
- `phpstan/phpstan` — Static analysis
- `szepeviktor/phpstan-wordpress` — WordPress stubs for PHPStan

# Webware Log: Copilot Agent Instructions

## Read this before acting: fleet working agreements

`vendor/webware/webware-tools/agent-working-agreements.md` is authoritative for how work is done
in this fleet, and it overrides anything else in this file or in any local note. Read it before
acting, and again before reporting. The non-negotiables:

- Do exactly the scope asked - only the files, branches and repositories named. No adjacent edits,
  no cleanup, no onboarding, no surveys.
- Anything else you notice gets one line at most, with no proposed fix.
- Never close a reply with a condensed restatement of it, and never present an inference as a
  measurement.
- Every change lands through a pull request. Releases and tags belong to the repository owner.

## PHPUnit Mock vs Stub Rules

PHPUnit 13 enforces a strict separation between mocks and stubs. Violating these rules produces `PHPUnit Notices` that cause test suite failures under `failOnNotice="true"` (configured in `phpunit.xml.dist`).

### Rules

- **Use `createStub()`** when the test double only needs to return values (`method()->willReturn()`, `method()->willReturnCallback()`). No expectations are configured.
- **Use `createMock()`** only when the test verifies behavior with `expects()` (e.g. `expects($this->once())`, `expects($this->never())`).
- **Never** call `createMock()` without also calling `expects()` on at least one method - PHPUnit 13 will issue a notice.
- **Remove** `/** @var ClassName&MockObject */` annotations and `MockObject` intersection types on variables created with `createStub()`.
- **Remove** the `use PHPUnit\Framework\MockObject\MockObject;` import from any file where no `createMock()` + `expects()` usage remains.

### Examples

```php
// CORRECT - stub returns value, no expectations
$container = $this->createStub(ContainerInterface::class);
$container->method('get')->willReturnCallback(static fn(string $id): mixed => ...);

// CORRECT - mock verifies behavior with expects()
$handler = $this->createMock(ErrorHandler::class);
$handler->expects($this->once())->method('attachListener');

// WRONG - createMock() with no expects() triggers PHPUnit notice
$logger = $this->createMock(Logger::class);
$logger->method('withName')->willReturn($logger); // should be createStub()
```

## PHPUnit Coverage Metadata Rules

`phpunit.xml.dist` has `requireCoverageMetadata="true"`. Every test class **must** have:

1. `#[CoversClass(ClassName::class)]` - one per source class under test.
2. `#[CoversMethod(ClassName::class, 'methodName')]` - one per public/protected method exercised.
3. `use PHPUnit\Framework\Attributes\CoversClass;` and `use PHPUnit\Framework\Attributes\CoversMethod;` imports.

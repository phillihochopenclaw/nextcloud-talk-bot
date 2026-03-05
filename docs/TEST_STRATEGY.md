# Test Strategy - Nextcloud Talk Bot

## Overview

This document defines the testing strategy for the Nextcloud Talk Bot application. It outlines our approach to ensuring code quality, reliability, and maintainability through comprehensive testing.

## Testing Philosophy

### Goals
1. **Quality Assurance**: Ensure the application works correctly under all expected conditions
2. **Regression Prevention**: Detect bugs before they reach production
3. **Documentation**: Tests serve as living documentation of expected behavior
4. **Confidence**: Enable safe refactoring and feature additions

### Principles
- **Test Pyramid**: Focus on unit tests, supplement with integration tests
- **Fast Feedback**: Unit tests must run in under 1 second each
- **Isolation**: Each test should be independent and isolated
- **Clarity**: Test names should clearly describe what is being tested

## Test Categories

### 1. Unit Tests

**Purpose**: Test individual classes and methods in isolation

**Location**: `tests/Unit/`

**Characteristics**:
- Fast execution (< 1s per test)
- No database/network dependencies
- Use mocks for external dependencies
- Test one thing per test method

**Coverage**:
- `MessageService`: Message processing logic, commands, echo mode
- `WebhookController`: Input validation, error handling, response format
- `SettingsController`: CRUD operations, validation

**Naming Convention**:
```
tests/Unit/
├── Controller/
│   ├── WebhookControllerTest.php
│   └── SettingsControllerTest.php
└── Service/
    └── MessageServiceTest.php
```

### 2. Integration Tests

**Purpose**: Test that components work together correctly

**Location**: `tests/Integration/`

**Characteristics**:
- May use in-memory SQLite database
- Test file system interactions
- Verify app structure and configuration
- Slower than unit tests but still fast (< 5s per test)

**Coverage**:
- App installation structure
- Service registration
- Route definitions
- Configuration loading

### 3. Functional Tests (Future)

**Purpose**: Test complete user workflows end-to-end

**Location**: `tests/Functional/`

**Status**: Not implemented in Sprint 1

**Planned Coverage**:
- Complete webhook request/response cycles
- Bot message flows
- Admin settings workflow

## Test Tools

### PHPUnit 10.x
- Primary testing framework
- Assertions and test organization
- Data providers for parameterized tests
- Code coverage reporting

### Mock Framework
- PHPUnit mocks for interfaces
- Custom mock classes for Nextcloud interfaces (MockRequest, MockConfig)

## Running Tests

### All Tests
```bash
./vendor/bin/phpunit
```

### Unit Tests Only
```bash
./vendor/bin/phpunit --testsuite Unit
```

### Integration Tests Only
```bash
./vendor/bin/phpunit --testsuite Integration
```

### With Coverage Report
```bash
./vendor/bin/phpunit --coverage-html coverage
```

### Specific Test File
```bash
./vendor/bin/phpunit tests/Unit/Service/MessageServiceTest.php
```

### Specific Test Method
```bash
./vendor/bin/phpunit --filter testProcessMessageEcho
```

## Code Coverage

### Targets (Sprint 1)
| Component | Target | Current |
|-----------|--------|---------|
| MessageService | 90% | - |
| WebhookController | 85% | - |
| SettingsController | 85% | - |
| **Overall** | **80%** | - |

### Coverage Commands
```bash
# Generate HTML report
./vendor/bin/phpunit --coverage-html coverage/html

# Generate Clover XML for CI
./vendor/bin/phpunit --coverage-clover coverage/clover.xml

# Show coverage in terminal
./vendor/bin/phpunit --coverage-text
```

### Exclusions
- `lib/Listener/` - Event listeners (will be tested via integration tests)
- Generated code
- Third-party libraries

## Test Data Management

### In-Memory Database
- Use SQLite in-memory database for integration tests
- Schema created fresh for each test run
- No persistent state between tests

### Fixtures
- Create test fixtures within test methods
- Use factory methods for common test data
- Clean up after each test

## Mocking Strategy

### What to Mock
- Database connections (`IDBConnection`)
- Configuration (`IConfig`)
- Request objects (`IRequest`)
- Logger (`LoggerInterface`)

### What NOT to Mock
- Simple value objects
- Pure PHP functions (e.g., `json_encode`)
- Constants

### Mock Implementation
```php
// Using PHPUnit mocks
$logger = $this->createMock(LoggerInterface::class);

// Using custom mocks for complex interfaces
$config = new MockConfig(['key' => 'value']);
$request = new MockRequest(['param' => 'value']);
```

## Test Patterns

### Arrange-Act-Assert (AAA)
```php
public function testSomething(): void {
    // Arrange
    $service = new MessageService($logger);
    
    // Act
    $result = $service->processMessage('Hello', 'user');
    
    // Assert
    $this->assertEquals('reply', $result['action']);
}
```

### Data Providers
```php
/**
 * @dataProvider provideValidCommands
 */
public function testCommands(string $command, string $expected): void {
    // ...
}

public static function provideValidCommands(): array {
    return [
        'help command' => ['/help', 'Available commands'],
        'ping command' => ['/ping', 'Pong'],
        'status command' => ['/status', 'running normally'],
    ];
}
```

### Exception Testing
```php
public function testInvalidInputThrowsException(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid input');
    
    $service->doSomething('invalid');
}
```

## Continuous Integration

### GitHub Actions (Future)
- Run tests on every push
- Run tests on every pull request
- Generate coverage reports
- Fail build if coverage drops below threshold

### Pre-Commit Hooks (Recommended)
```bash
# Run quick tests before commit
./vendor/bin/phpunit --testsuite Unit
```

## Quality Gates

### Definition of Done for Testing
- [ ] All new code has corresponding tests
- [ ] All tests pass
- [ ] Code coverage meets threshold (80%+)
- [ ] No critical code paths are untested
- [ ] Edge cases are covered
- [ ] Error handling is tested
- [ ] Tests are readable and maintainable

## Test Maintenance

### Regular Tasks
- Update tests when changing behavior
- Remove obsolete tests
- Refactor duplicate test code into reusable methods
- Review and update coverage targets quarterly

### Test Smells to Avoid
- Tests that depend on execution order
- Tests that modify global state
- Over-mocking (mocking everything)
- Under-mocking (external dependencies leak)
- Tests that test implementation, not behavior
- Slow tests (> 1s for unit tests)

## Documentation

### Test Documentation
Each test suite should have:
- Class-level docblock explaining what's being tested
- Method-level docblock for complex test logic
- Clear test method names: `test[Scenario][ExpectedResult]`

### Example
```php
/**
 * Unit tests for MessageService
 * 
 * Tests message processing logic including:
 * - Echo mode
 * - Command handling
 * - Configuration management
 */
class MessageServiceTest extends TestCase {
    /**
     * Test that the /ping command returns a pong response
     */
    public function testPingCommandReturnsPong(): void {
        // ...
    }
}
```

## Sprint 1 Test Deliverables

### Completed
- [x] Test strategy documented
- [x] PHPUnit configuration (phpunit.xml)
- [x] Test framework setup (TestCase, MockRequest, MockConfig)
- [x] Unit tests for MessageService
- [x] Unit tests for WebhookController
- [x] Unit tests for SettingsController
- [x] Integration tests for app installation

### Pending
- [ ] Run tests and verify all pass
- [ ] Generate coverage report
- [ ] Document coverage results

## Future Improvements

### Sprint 2+
- Functional tests with Nextcloud test framework
- API documentation tests
- Performance tests for high-volume webhooks
- Security tests (input validation, XSS prevention)

### Tooling
- PHPStan integration for static analysis
- PHP-CS-Fixer for code style
- Infection for mutation testing
# Contributing Guide

Guidelines for contributing to Enteraksi LMS.

---

## Getting Started

1. Set up your development environment following the [Installation Guide](./getting-started/installation.md)
2. Read the [Architecture Overview](./architecture/overview.md)
3. Familiarize yourself with the [Code Style](#code-style)

---

## Development Workflow

### 1. Create a Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/issue-description
```

**Branch naming conventions:**
- `feature/` - New features
- `fix/` - Bug fixes
- `refactor/` - Code refactoring
- `docs/` - Documentation changes
- `test/` - Test additions/fixes

### 2. Make Changes

- Write code following the [Code Style](#code-style)
- Add tests for new functionality
- Update documentation if needed

### 3. Run Tests

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=CourseTest
```

### 4. Check Code Style

```bash
# PHP
vendor/bin/pint

# JavaScript/TypeScript
npm run lint
```

### 5. Commit Changes

```bash
git add .
git commit -m "feat: add course duplication feature"
```

**Commit message format:**
```
type: short description

[optional body]

[optional footer]
```

**Types:**
- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation
- `style` - Formatting, no code change
- `refactor` - Code restructuring
- `test` - Adding tests
- `chore` - Maintenance tasks

### 6. Push and Create PR

```bash
git push origin feature/your-feature-name
```

Create a Pull Request with:
- Clear title
- Description of changes
- Related issue numbers
- Screenshots (for UI changes)

---

## Code Style

### PHP

We use [Laravel Pint](https://laravel.com/docs/pint) with Laravel preset.

```bash
# Check style
vendor/bin/pint --test

# Fix style
vendor/bin/pint
```

**Key conventions:**
- PSR-12 coding standard
- Type hints for parameters and return types
- PHPDoc for complex methods
- No `else` after `return`

```php
// Good
public function find(int $id): ?Course
{
    $course = Course::find($id);

    if (! $course) {
        return null;
    }

    return $course;
}

// Bad
public function find($id)
{
    $course = Course::find($id);

    if (! $course) {
        return null;
    } else {
        return $course;
    }
}
```

### JavaScript/TypeScript

We use ESLint and Prettier.

```bash
# Check style
npm run lint

# Fix style
npm run lint -- --fix
```

**Key conventions:**
- Vue 3 Composition API with `<script setup>`
- TypeScript for type safety
- Props defined with `defineProps<T>()`
- Composables for shared logic

```vue
<!-- Good -->
<script setup lang="ts">
interface Props {
  course: Course;
  canEdit: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update', course: Course): void;
}>();
</script>
```

### CSS/Tailwind

- Use Tailwind utility classes
- Extract repeated patterns to components
- Use `@apply` sparingly
- Follow mobile-first approach

```vue
<!-- Good: Tailwind utilities -->
<div class="flex items-center gap-4 p-4 rounded-lg bg-white shadow">

<!-- Avoid: Excessive @apply -->
<style>
.card {
  @apply flex items-center gap-4 p-4 rounded-lg bg-white shadow;
}
</style>
```

---

## Testing Guidelines

### PHP Tests

Use PHPUnit with Laravel's testing helpers.

```php
class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_course(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);

        $response = $this->actingAs($user)
            ->post('/courses', [
                'title' => 'Test Course',
                'difficulty_level' => 'beginner',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', ['title' => 'Test Course']);
    }
}
```

**Conventions:**
- Test method names describe behavior
- Use factories for test data
- Test happy paths and edge cases
- Test authorization separately

### What to Test

- [ ] Controllers return correct responses
- [ ] Validation rules work correctly
- [ ] Authorization policies deny/allow correctly
- [ ] Model relationships work
- [ ] Business logic in services

---

## Database Changes

### Creating Migrations

```bash
php artisan make:migration add_field_to_courses_table
```

**Conventions:**
- Descriptive migration names
- Always include `down()` method
- Test migrations both ways

```php
public function up(): void
{
    Schema::table('courses', function (Blueprint $table) {
        $table->boolean('is_featured')->default(false)->after('status');
    });
}

public function down(): void
{
    Schema::table('courses', function (Blueprint $table) {
        $table->dropColumn('is_featured');
    });
}
```

### Creating Factories

```bash
php artisan make:factory CourseFactory
```

Include useful states:

```php
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published']);
    }
}
```

---

## Documentation

### When to Update Docs

- Adding new features
- Changing existing behavior
- Adding new configuration options
- Creating new commands

### Documentation Structure

```
docs/
├── getting-started/   # Tutorials for new developers
├── guides/            # How-to guides for tasks
├── reference/         # Technical reference
├── architecture/      # System design
└── adr/               # Decision records
```

### Writing ADRs

When making significant architectural decisions:

1. Create `docs/adr/NNN-title.md`
2. Follow the template:
   - Status, Date, Deciders
   - Context
   - Decision
   - Consequences
   - Alternatives Considered

---

## Pull Request Checklist

Before submitting:

- [ ] Code follows style guidelines
- [ ] Tests pass locally
- [ ] New features have tests
- [ ] Documentation updated (if needed)
- [ ] No console.log or dd() left behind
- [ ] Migration has down() method
- [ ] PR description explains changes

---

## Code Review

### As Author

- Keep PRs focused and small
- Respond to feedback promptly
- Don't take criticism personally

### As Reviewer

- Be respectful and constructive
- Explain the "why" behind suggestions
- Approve when changes are acceptable
- Focus on:
  - Correctness
  - Security
  - Performance
  - Maintainability

---

## Getting Help

- Read existing documentation
- Check similar code in the codebase
- Ask in team chat
- Create an issue for discussion

---

## Recognition

Contributors are recognized in:
- Git commit history
- Pull request credits
- Release notes (for significant contributions)

Thank you for contributing to Enteraksi LMS!

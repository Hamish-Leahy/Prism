# Contributing to Prism Browser

Thank you for your interest in contributing to Prism Browser! We welcome contributions from the community and appreciate your help in making Prism better.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Contribution Guidelines](#contribution-guidelines)
- [Pull Request Process](#pull-request-process)
- [Issue Guidelines](#issue-guidelines)
- [Development Workflow](#development-workflow)
- [Testing Guidelines](#testing-guidelines)
- [Documentation](#documentation)
- [Community](#community)

## Code of Conduct

This project and everyone participating in it is governed by our [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to conduct@prism-browser.com.

## Getting Started

### Prerequisites

Before you begin, ensure you have the following installed:

- **Node.js** 18+ and npm
- **PHP** 8.1+ and Composer
- **Git** for version control
- **Docker** (optional, for containerized development)

### Development Setup

1. **Fork and Clone**
   ```bash
   # Fork the repository on GitHub, then clone your fork
   git clone https://github.com/YOUR_USERNAME/prism-browser.git
   cd prism-browser
   
   # Add upstream remote
   git remote add upstream https://github.com/prism-browser/prism-browser.git
   ```

2. **Install Dependencies**
   ```bash
   # Backend dependencies
   cd backend
   composer install
   
   # Frontend dependencies
   cd ../frontend
   npm install
   ```

3. **Environment Setup**
   ```bash
   # Copy environment files
   cp backend/env.example backend/.env
   cp frontend/.env.example frontend/.env
   
   # Configure your environment variables
   # Edit backend/.env and frontend/.env as needed
   ```

4. **Start Development Servers**
   ```bash
   # Terminal 1: Backend
   cd backend
   php -S localhost:8000
   
   # Terminal 2: Frontend
   cd frontend
   npm run electron:dev
   ```

## Contribution Guidelines

### Types of Contributions

We welcome several types of contributions:

- **🐛 Bug Fixes**: Fix existing issues
- **✨ New Features**: Add new functionality
- **📚 Documentation**: Improve or add documentation
- **🧪 Tests**: Add or improve test coverage
- **🎨 UI/UX**: Improve user interface and experience
- **⚡ Performance**: Optimize performance
- **🔒 Security**: Improve security features
- **🌐 Internationalization**: Add language support

### Contribution Areas

#### Backend (PHP)
- API endpoints and controllers
- Service layer improvements
- Database models and migrations
- Middleware and authentication
- Rendering engine implementations

#### Frontend (React/TypeScript)
- React components and hooks
- UI/UX improvements
- Electron main process
- TypeScript type definitions
- State management

#### Engines
- Chromium engine integration
- Firefox engine integration
- Custom Prism engine features
- WebRTC, WebAssembly, Service Worker support

#### Documentation
- API documentation
- User guides
- Developer documentation
- Code comments and docblocks

## Pull Request Process

### Before Submitting

1. **Check Existing Issues**
   - Search for existing issues and PRs
   - Comment on issues you plan to work on
   - Ask questions if anything is unclear

2. **Create a Branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/issue-number-description
   ```

3. **Make Your Changes**
   - Write clean, readable code
   - Follow our coding standards
   - Add tests for new functionality
   - Update documentation as needed

4. **Test Your Changes**
   ```bash
   # Backend tests
   cd backend && composer test
   
   # Frontend tests
   cd frontend && npm test
   
   # Linting
   composer lint && npm run lint
   ```

### Submitting a Pull Request

1. **Push Your Changes**
   ```bash
   git add .
   git commit -m "feat: add amazing new feature"
   git push origin feature/your-feature-name
   ```

2. **Create Pull Request**
   - Use our [PR template](.github/pull_request_template.md)
   - Provide a clear description
   - Link related issues
   - Add screenshots for UI changes

3. **Respond to Feedback**
   - Address review comments
   - Make requested changes
   - Keep the PR up to date with main

### PR Requirements

- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Tests added/updated and passing
- [ ] Documentation updated
- [ ] No merge conflicts
- [ ] Commit messages follow conventional format

## Issue Guidelines

### Creating Issues

When creating issues, please:

1. **Search First**: Check if the issue already exists
2. **Use Templates**: Use our issue templates
3. **Be Specific**: Provide clear, detailed descriptions
4. **Include Context**: Add relevant information and screenshots
5. **Label Appropriately**: Use correct labels

### Issue Types

- **🐛 Bug Report**: Something isn't working
- **✨ Feature Request**: Suggest a new feature
- **📚 Documentation**: Documentation improvements
- **❓ Question**: Ask a question
- **🔒 Security**: Report security vulnerabilities

### Bug Reports

When reporting bugs, include:

- **Environment**: OS, Node.js version, PHP version
- **Steps to Reproduce**: Clear, numbered steps
- **Expected Behavior**: What should happen
- **Actual Behavior**: What actually happens
- **Screenshots**: Visual evidence if applicable
- **Logs**: Relevant error messages or logs

## Development Workflow

### Git Workflow

We use a feature branch workflow:

1. **Main Branch**: `main` - production-ready code
2. **Develop Branch**: `develop` - integration branch
3. **Feature Branches**: `feature/description` - new features
4. **Hotfix Branches**: `hotfix/description` - critical fixes

### Commit Message Format

We use [Conventional Commits](https://conventionalcommits.org/):

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(engine): add WebRTC support to Prism engine
fix(ui): resolve tab switching animation issue
docs(api): update authentication endpoint documentation
```

### Code Style

#### PHP (Backend)
- Follow PSR-12 coding standards
- Use type declarations
- Write descriptive variable names
- Add docblocks for public methods
- Use dependency injection

#### TypeScript/React (Frontend)
- Use TypeScript strict mode
- Follow React best practices
- Use functional components with hooks
- Implement proper error boundaries
- Use Tailwind CSS for styling

## Testing Guidelines

### Test Coverage

We aim for high test coverage:

- **Unit Tests**: Individual functions and methods
- **Integration Tests**: Component interactions
- **End-to-End Tests**: Complete user workflows
- **Performance Tests**: Load and stress testing

### Running Tests

```bash
# Backend tests
cd backend
composer test                    # All tests
composer test:engines           # Engine tests only
composer test:coverage          # With coverage report

# Frontend tests
cd frontend
npm test                        # All tests
npm run test:unit              # Unit tests only
npm run test:e2e               # E2E tests
npm run test:coverage          # With coverage report
```

### Writing Tests

#### PHP Tests (PHPUnit)
```php
<?php
namespace Prism\Backend\Tests\Services;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\ExampleService;

class ExampleServiceTest extends TestCase
{
    public function testExampleMethod(): void
    {
        $service = new ExampleService();
        $result = $service->exampleMethod('input');
        
        $this->assertEquals('expected', $result);
    }
}
```

#### TypeScript Tests (Vitest)
```typescript
import { describe, it, expect } from 'vitest';
import { ExampleComponent } from './ExampleComponent';

describe('ExampleComponent', () => {
  it('should render correctly', () => {
    // Test implementation
    expect(true).toBe(true);
  });
});
```

## Documentation

### Documentation Standards

- Use clear, concise language
- Include code examples
- Keep documentation up to date
- Use proper markdown formatting
- Add diagrams for complex concepts

### Documentation Types

- **API Documentation**: Endpoint descriptions and examples
- **User Guides**: Step-by-step instructions
- **Developer Guides**: Technical implementation details
- **Code Comments**: Inline documentation

### Updating Documentation

When making changes:

1. Update relevant documentation
2. Add new documentation for new features
3. Remove outdated information
4. Ensure all links work
5. Test documentation examples

## Community

### Getting Help

- **GitHub Discussions**: General questions and discussions
- **Discord**: Real-time chat and support
- **Email**: support@prism-browser.com
- **Documentation**: Check our docs first

### Recognition

Contributors are recognized in:

- **CONTRIBUTORS.md**: List of all contributors
- **Release Notes**: Feature contributors
- **GitHub**: Contributor statistics
- **Website**: Featured contributors

### Mentorship

We offer mentorship for:

- New contributors
- Complex features
- Architecture decisions
- Code reviews

## Release Process

### Versioning

We use [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Schedule

- **Major Releases**: Quarterly
- **Minor Releases**: Monthly
- **Patch Releases**: As needed
- **Pre-releases**: For testing

### Release Checklist

- [ ] All tests passing
- [ ] Documentation updated
- [ ] Changelog updated
- [ ] Version bumped
- [ ] Release notes prepared
- [ ] Security review completed

## Security

### Reporting Security Issues

**DO NOT** report security vulnerabilities through public GitHub issues.

Instead, please:

1. Email security@prism-browser.com
2. Include detailed information
3. Allow time for response
4. Follow responsible disclosure

### Security Guidelines

- Keep dependencies updated
- Use secure coding practices
- Validate all inputs
- Implement proper authentication
- Follow OWASP guidelines

## Questions?

If you have any questions about contributing:

- Check our [FAQ](docs/faq.md)
- Join our [Discord](https://discord.gg/prism-browser)
- Open a [GitHub Discussion](https://github.com/prism-browser/prism-browser/discussions)
- Email us at contribute@prism-browser.com

---

Thank you for contributing to Prism Browser! 🎉

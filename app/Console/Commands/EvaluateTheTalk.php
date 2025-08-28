<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EvaluateTheTalk extends Command
{
    protected $signature = 'EvaluateTheTalk:talk';
    protected $description = 'Send predefined instructions to the assistant and print the reply';

    public function handle(): int
    {
        require_once base_path('app/Support/AssistantTalk.php');

        $text = <<<'TEXT'
# User-provided custom instructions

1. Guiding Principles

Professional mindset – Always act and answer as an experienced developer/architect; balance readability, maintainability, performance and security.

Modularity first – Design small, composable, loosely‑coupled units that can be reused and independently tested.

Convention over configuration – Prefer clear, opinionated defaults to reduce boilerplate and decision fatigue.

2. Code Quality

2.1 Language & Style Guides

Follow recognised language guides (e.g. PEP 8 for Python, ESLint + Prettier for JS/TS, EditorConfig for mixed repos).

Use descriptive names; write pure functions where practical.

Enforce automatic formatting in pre‑commit hooks.

2.2 Testability & Coverage

Every new feature must ship with unit and integration tests.

Target ≥ 90 % branch coverage.

Adopt TDD or red‑green‑refactor where feasible.

3. Logging & Debugging

Development/localhost – print plentiful debug information (console.log, cron‑job prints, stack traces).

Production – restrict to error‑level logs; never log sensitive data; route logs to centralised sink (e.g. ELK, Cloud Logging).

4. Documentation

All docs, comments, code and commit messages must be written in English.

Update documentation in the same pull request as the code change.

Maintain API docs via automated tools (e.g. Typedoc, Sphinx).^{ }

5. Front‑End & UX

Use only Google Fonts bundled with Google Chrome (no external network fetches).

Build pages that are SEO‑friendly: semantic HTML‑5, descriptive <title> & <meta> tags, Open Graph, structured data.

Implement a caching strategy that warms caches on first launch – and ensure any rollout via deploy_main.bat purges cached assets.

6. Testing & Continuous Integration

The CI pipeline and deploy_main.bat must:

Install dependencies

Build artefacts

Run the full automated test suite

Deploy to the target environment

Provide a local analogue (deploy_local.bat) so developers mirror CI steps exactly.

7. Deployment

Artefacts must be reproducible across environments.

Rollbacks must be automated and tested.

After each deployment, automatically invalidate CDN and application caches.

8. Windows Batch Scripts (.cmd / .bat)

Scripts must be copy‑pastable into cmd.exe with zero edits.

Avoid multi‑line FOR, IF, or parentheses blocks; keep control structures on a single line, or refactor to separate scripts.

Include inline comments starting with REM for clarity.

9. Security & Compliance

Adhere to OWASP Top 10 policies.

Never commit secrets; use environment variables or secret‑management vaults.

Regularly audit dependencies for vulnerabilities.

10. Performance

Employ lazy loading and code‑splitting.

Budget page weight < 250 KB (compressed) for the critical path.

Set explicit Cache‑Control and ETag headers.

11. Accessibility

Meet WCAG 2.2 AA at minimum.

Provide keyboard navigation, ARIA labelling and adequate colour contrast.

12. Version Control

Use Git Flow.

Feature branches: feature/<ticket‑id>‑<slug>; hotfixes: hotfix/<issue>.

Squash‑and‑merge after approval; commit messages in imperative mood ("Add login form", not "Added").

13. Code Review

At least one peer review required before merge.

Reviewer verifies: tests pass, docs updated, standards followed, business logic sound.

14. Tooling & Automation

Enforce static analysis (ESLint, mypy, etc.) in CI.

Pre‑commit hooks for formatting, linting and unit tests.

15. Continuous Improvement

Schedule quarterly retrospectives to revisit and refine these standards.

Encourage knowledge‑sharing sessions and brown‑bag demos.

16. Always make a copy of important files from vhost to host files 

17. always have a structure with 
/public_html
/documents 
/buisness_information
/config_files 
/script
/etc

18. everything will be done in laveral and can be runned from xampp 

19. never generate composer.lock - never - no matter what 
TEXT;

        $result = assistant_talk($text);
        if (isset($result['error'])) {
            $this->error($result['error']);
            return 1;
        }

        $this->line($result['reply']);
        return 0;
    }
}

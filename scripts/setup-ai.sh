#!/bin/bash
set -e

echo "=== Setup AI-First + PREVC V5 — InteraZap ==="
echo ""

# Symlink
if [ -L "CLAUDE.md" ]; then
  echo "✅ CLAUDE.md → $(readlink CLAUDE.md)"
else
  ln -sf AGENTS.md CLAUDE.md
  echo "✅ CLAUDE.md criado → AGENTS.md"
fi

# Estrutura de pastas
dirs=(
  ".claude/commands" ".claude/hooks" ".claude/skills"
  ".context/ARCHITECTURE"
  ".context/DOCS/FEATURES" ".context/DOCS/TASKS" ".context/DOCS/PRDS"
  ".context/DOCS/CHANGELOG" ".context/DOCS/MEMORY"
  ".context/WORKFLOW"
  "scripts"
)

echo ""
echo "📁 Estrutura de pastas:"
for dir in "${dirs[@]}"; do
  if [ -d "$dir" ]; then
    echo "  ✅ $dir"
  else
    mkdir -p "$dir"
    echo "  📁 Criado: $dir"
  fi
done

echo ""
echo "📄 Raiz:"
test -f AGENTS.md && echo "  ✅ AGENTS.md" || echo "  ❌ AGENTS.md FALTANDO"
test -L CLAUDE.md && echo "  ✅ CLAUDE.md (symlink)" || echo "  ❌ CLAUDE.md FALTANDO"
test -f TUTORIAL.md && echo "  ✅ TUTORIAL.md" || echo "  ⚠️  TUTORIAL.md não encontrado"

echo ""
echo "⚙️ Settings & Hooks:"
test -f .claude/settings.json && echo "  ✅ .claude/settings.json" || echo "  ❌ .claude/settings.json FALTANDO"
test -f .claude/hooks/router.js && echo "  ✅ .claude/hooks/router.js" || echo "  ❌ .claude/hooks/router.js FALTANDO"

echo ""
echo "📋 Commands:"
for f in new-feature review-feature decompose validate-tasks implement-task validate confirm-task feature-status review-phase validate-phase confirm-phase; do
  test -f ".claude/commands/$f.md" && echo "  ✅ $f.md" || echo "  ❌ $f.md FALTANDO"
done

echo ""
echo "🛠️  Skills:"
for f in tace-framework decompose-feature write-feature detect-project; do
  test -f ".claude/skills/$f/$f.md" && echo "  ✅ $f/$f.md" || echo "  ❌ $f/$f.md FALTANDO"
done

echo ""
echo "🤖 Agents (.context/AGENTS/):"
agent_count=$(ls .context/AGENTS/*.md 2>/dev/null | wc -l | tr -d ' ')
echo "  ✅ $agent_count agents encontrados"
for f in ORCHESTRATOR PM PLAN ARCHITECT REVIEWER BACKEND GATEWAY FRONTEND DBA DEV QA DEBUG DOC GIT_COMMIT DESIGNER; do
  test -f ".context/AGENTS/$f.md" && echo "  ✅ $f.md" || echo "  ⚠️  $f.md não encontrado"
done

echo ""
echo "📚 Skills (.context/SKILLS/):"
skill_count=$(ls -d .context/SKILLS/*/ 2>/dev/null | wc -l | tr -d ' ')
echo "  ✅ $skill_count skills encontradas"
for f in laravel-especialista nestjs-especialista angular-especialista code-review-confiavel workflow-prevc skill-architect; do
  test -d ".context/SKILLS/$f" && echo "  ✅ $f/" || echo "  ⚠️  $f/ não encontrado"
done

echo ""
echo "📁 Workflow:"
test -f ".context/WORKFLOW/PREVC.md" && echo "  ✅ PREVC.md" || echo "  ❌ PREVC.md FALTANDO"
test -f ".context/WORKFLOW/validation-flow.md" && echo "  ✅ validation-flow.md" || echo "  ❌ validation-flow.md FALTANDO"

echo ""
echo "🏗️  Architecture:"
for f in architecture.mmd modules.yaml modules.mmd dependencies.yaml project-state.yaml project-brain.yaml context-version.yaml user-flow.mmd; do
  test -f ".context/ARCHITECTURE/$f" && echo "  ✅ $f" || echo "  ❌ $f FALTANDO"
done

echo ""
echo "📜 Changelog:"
test -f ".context/DOCS/CHANGELOG/_TEMPLATE.md" && echo "  ✅ _TEMPLATE.md" || echo "  ❌ _TEMPLATE.md FALTANDO"
test -f ".context/DOCS/CHANGELOG/README.md" && echo "  ✅ README.md" || echo "  ❌ README.md FALTANDO"
changelog_count=$(ls .context/DOCS/CHANGELOG/2*.md 2>/dev/null | wc -l | tr -d ' ')
echo "  ✅ $changelog_count arquivo(s) de changelog"

echo ""
echo "🧠 Memory:"
test -f ".context/DOCS/MEMORY/_TEMPLATE.md" && echo "  ✅ _TEMPLATE.md" || echo "  ❌ _TEMPLATE.md FALTANDO"
test -f ".context/DOCS/MEMORY/README.md" && echo "  ✅ README.md" || echo "  ❌ README.md FALTANDO"
memory_count=$(ls .context/DOCS/MEMORY/2*.md 2>/dev/null | wc -l | tr -d ' ')
echo "  ✅ $memory_count arquivo(s) de memory"

echo ""
echo "📋 Templates:"
test -f ".context/DOCS/FEATURES/_TEMPLATE.md" && echo "  ✅ FEATURES/_TEMPLATE.md" || echo "  ❌"
test -f ".context/DOCS/TASKS/_TEMPLATE.md" && echo "  ✅ TASKS/_TEMPLATE.md" || echo "  ❌"
test -f ".context/DOCS/PRDS/_TEMPLATE.md" && echo "  ✅ PRDS/_TEMPLATE.md" || echo "  ❌"

echo ""
echo "=== FIM ==="
echo ""
echo "Para começar:"
echo "  /new-feature [nome-da-feature]"
echo ""
echo "Workflow PREVC:"
echo "  Planning → Review → Execution → Validation → Confirm"
echo "  Documentação: .context/WORKFLOW/PREVC.md"

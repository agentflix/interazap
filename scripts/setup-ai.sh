#!/bin/bash
set -e

echo "🚀 Setup AI-First + PREVC..."

# Symlink
ln -sf AGENTS.md CLAUDE.md
echo "✅ CLAUDE.md → AGENTS.md"

# Estrutura
dirs=(
  ".claude/agents" ".claude/commands" ".claude/hooks" ".claude/skills"
  ".context/ARCHITECTURE"
  ".context/DOCS/FEATURES" ".context/DOCS/TASKS" ".context/DOCS/PRDS"
  ".context/DOCS/CHANGELOG" ".context/DOCS/MEMORY"
  ".context/LAYOUT" ".context/WORKFLOW"
  "scripts"
)

for dir in "${dirs[@]}"; do
  if [ ! -d "$dir" ]; then
    mkdir -p "$dir"
    echo "📁 Criado: $dir"
  else
    echo "✅ Existe: $dir"
  fi
done

# PLANS → FEATURES
if [ -d ".context/DOCS/PLANS" ]; then
  cp -rn .context/DOCS/PLANS/* .context/DOCS/FEATURES/ 2>/dev/null || true
  mv .context/DOCS/PLANS ".context/DOCS/PLANS_BACKUP_$(date +%Y%m%d)"
  echo "🔄 PLANS → FEATURES"
fi

echo ""
echo "✅ Estrutura pronta!"
echo ""
echo "📋 Workflow PREVC:"
echo "  /new-feature [nome]                    — Planning"
echo "  /review-feature [nome]                 — Review"
echo "  /decompose [nome]                      — Tasks T.A.C.E"
echo "  /validate-tasks [nome]                 — Validar tasks"
echo "  /implement-task [feature] [TASK-NNN]   — Execution"
echo "  /validate [feature] [TASK-NNN]         — Validation"
echo "  /confirm-task [feature] [TASK-NNN]     — Confirm + Changelog + Memory"
echo "  /feature-status [nome]                 — Progresso"

#!/usr/bin/env node
/**
 * Router Hook para AgentFlix
 * Lê a mensagem do usuário via stdin, avalia skills, e retorna contexto
 */

const fs = require('fs');
const path = require('path');

const PROJECT_ROOT = path.join(__dirname, '..', '..');

function readStdin() {
  return new Promise((resolve, reject) => {
    let data = '';
    process.stdin.on('readable', () => {
      let chunk;
      while ((chunk = process.stdin.read()) !== null) {
        data += chunk;
      }
    });
    process.stdin.on('end', () => resolve(data));
    process.stdin.on('error', reject);
  });
}

function readFile(filePath) {
  try {
    return fs.readFileSync(filePath, 'utf8');
  } catch {
    return null;
  }
}

function extractUserMessage(stdinData) {
  try {
    const parsed = JSON.parse(stdinData);
    // UserPromptSubmit hook pode passar em diferentes campos
    const msg = parsed.arguments || parsed.text || parsed.userMessage || parsed.content || parsed.message;
    if (msg) return msg;

    // Se tem session_id, pode ser o payload completo - tenta extrair text
    if (parsed.session_id && stdinData.includes('"text"')) {
      const match = stdinData.match(/"text"\s*:\s*"([^"]+)"/);
      if (match) return match[1];
    }

    return stdinData;
  } catch {
    return stdinData;
  }
}

function findMatchingSkill(userMessage) {
  const message = userMessage.toLowerCase();

  // Ordenado: específicos primeiro, genéricos depois
  const skillMap = [
    { keywords: ['plano', 'planejar'], skill: 'create-plan' },
    { keywords: ['prd', 'product requirements', 'requisitos'], skill: 'generate-prd' },
    { keywords: ['task', 'tarefa', 'subtask'], skill: 'create-task' },
    { keywords: ['mockup', 'wireframe', 'layout'], skill: 'generate-mockup' },
    { keywords: ['diagrama', 'arquitetura', 'fluxo'], skill: 'generate-diagram' },
    { keywords: ['tdd', 'test-first', 'red-green', 'testes unitários'], skill: 'tdd' },
    { keywords: ['e2e', 'end-to-end', 'playwright', 'teste completo'], skill: 'e2e-testing' },
    { keywords: ['frontend', 'angular', 'componente', 'ui', 'interface'], skill: 'angular-architect' },
    { keywords: ['backend', 'laravel', 'php', 'api', 'modelo'], skill: 'laravel-specialist' },
    { keywords: ['frontend design', 'interface', 'página', 'web design'], skill: 'frontend-design' },
    { keywords: ['revisar design', 'review design', 'ux', 'acessibilidade'], skill: 'web-design-reviewer' },
    { keywords: ['commit', 'git'], skill: 'git-commit' },
    { keywords: ['documentar', 'jsdoc', 'docs', 'documentação'], skill: 'jsdoc-typescript-docs' },
    { keywords: ['testar', 'testing', 'playwright', 'webapp'], skill: 'webapp-testing' },
    { keywords: ['nova feature', 'criar feature', 'implementar', 'adicionar', 'criar componente'], skill: 'brainstorming' },
  ];

  for (const item of skillMap) {
    if (item.keywords.some(kw => message.includes(kw))) {
      return item.skill;
    }
  }

  return null;
}

function loadORCHESTRATORInfo() {
  const orchPath = path.join(PROJECT_ROOT, '.claude', 'agents', 'ORCHESTRATOR.md');
  const content = readFile(orchPath);
  if (!content) return '';

  // Extrai informações relevantes
  const lines = content.split('\n');
  let info = '\n### @ORCHESTRATOR — Task Coordinator\n';
  info += '- **Role:** Coordena tarefas complexas multi-agente\n';
  info += '- **Delegação:** DBA → BACKEND → GATEWAY → FRONTEND → QA → DOC\n';
  info += '- **Regra:** Nunca implementa código diretamente — sempre delega\n';
  info += '- **Trigger:** Tarefas cross-layer, features épicas\n';

  return info;
}

function loadAgentsInfo() {
  const agentsPath = path.join(PROJECT_ROOT, 'CLAUDE.md');
  const content = readFile(agentsPath);
  if (!content) return '';

  // Extrai tabela de agentes
  let info = '\n### Agentes Disponíveis\n';
  info += '| Agent | Role | Trigger |\n';
  info += '|-------|------|--------|\n';
  info += '| @ORCHESTRATOR | Task coordinator | Complex multi-agent tasks |\n';
  info += '| @DEV | Full-stack implementation | Cross-layer features |\n';
  info += '| @BACKEND | Laravel DDD specialist | Backend tasks |\n';
  info += '| @FRONTEND | Angular specialist | Frontend tasks |\n';
  info += '| @DBA | Database design | Migrations and schema |\n';
  info += '| @REVIEWER | Code review | After QA |\n';
  info += '| @QA | Quality audit | After gates |\n';

  return info;
}

function determineComplexity(userMessage) {
  const message = userMessage.toLowerCase();

  const complexIndicators = ['módulo', 'múltiplas', 'multi', 'backend', 'frontend', 'gateway', 'database', ' schema', 'arquitetura', 'refatorar', 'dashboard', 'relatório'];
  const simpleIndicators = ['bug', 'fix', 'corrigir', 'erro', 'ajuste', 'pequeno', 'simples'];

  let complexScore = complexIndicators.filter(i => message.includes(i)).length;
  let simpleScore = simpleIndicators.filter(i => message.includes(i)).length;

  return complexScore > simpleScore ? 'complex' : 'simple';
}

async function main() {
  try {
    const stdinData = await readStdin();
    const userMessage = extractUserMessage(stdinData);

    if (!userMessage || userMessage.trim().length === 0) {
      process.stdout.write(JSON.stringify({ continue: true }));
      return;
    }

    const skill = findMatchingSkill(userMessage);
    const complexity = determineComplexity(userMessage);

    let additionalContext = '';
    let action = '';

    if (skill) {
      action = `SKILL_MATCH`;
      additionalContext = `
## Análise Automática

**Skill encontrada:** ${skill}
**Complexidade:** ${complexity}

Invoke via: /${skill}

---`;
    } else if (complexity === 'complex') {
      action = `ORCHESTRATOR_NEEDED`;
      additionalContext = `
## Análise Automática

**Skill:** Nenhuma compatível
**Complexidade:** COMPLEXA (multi-camada)
**Recomendação:** Invocar @ORCHESTRATOR

---`;
    } else {
      action = `DIRECT_RESPONSE`;
      additionalContext = `
## Análise Automática

**Skill:** Nenhuma necessária
**Complexidade:** Simples
**Recomendação:** Responda diretamente ou invoque @DEV

---`;
    }

    // Carrega info dos agents
    const orchInfo = loadORCHESTRATORInfo();
    const agentsInfo = loadAgentsInfo();

    // Output JSON para o hook
    const output = {
      continue: true,
      hookSpecificOutput: {
        hookEventName: 'UserPromptSubmit',
        additionalContext: (additionalContext + orchInfo + agentsInfo).trim()
      },
      systemMessage: `Router: ${action} for: "${userMessage.substring(0, 50)}..."`
    };

    process.stdout.write(JSON.stringify(output));

  } catch (error) {
    // Em caso de erro, deixar passar a mensagem normalmente
    process.stdout.write(JSON.stringify({ continue: true }));
  }
}

main();

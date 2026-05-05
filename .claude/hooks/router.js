#!/usr/bin/env node
/**
 * Router Hook — InteraZap
 *
 * Lê a mensagem do usuário via stdin, identifica a skill/agent adequado,
 * e injeta contexto adicional para o Claude Code.
 *
 * Integrado ao workflow PREVC.
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

function extractUserMessage(stdinData) {
  try {
    const parsed = JSON.parse(stdinData);
    const msg = parsed.arguments || parsed.text || parsed.userMessage || parsed.content || parsed.message;
    if (msg) return msg;

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

  const skillMap = [
    // PREVC Workflow
    { keywords: ['nova feature', 'criar feature', 'new feature', 'planejar feature'], skill: 'new-feature', agent: 'PM' },
    { keywords: ['revisar feature', 'review feature', 'aprovar feature'], skill: 'review-feature', agent: 'REVIEWER' },
    { keywords: ['decompor', 'decompose', 'quebrar em tasks', 'criar tasks'], skill: 'decompose', agent: 'ARCHITECT' },
    { keywords: ['validar tasks', 'validate tasks', 'checar tasks'], skill: 'validate-tasks', agent: 'QA' },
    { keywords: ['implementar task', 'implement task', 'executar task'], skill: 'implement-task', agent: 'DEV' },
    { keywords: ['validar implementação', 'validate', 'rodar gates', 'gates'], skill: 'validate', agent: 'QA' },
    { keywords: ['confirmar task', 'confirm task', 'fechar task'], skill: 'confirm-task', agent: 'DOC' },
    { keywords: ['status feature', 'feature status', 'progresso'], skill: 'feature-status', agent: 'PM' },

    // Especialidades InteraZap
    { keywords: ['arquitetura', 'architecture', 'diagrama', 'módulos', 'bounded context'], skill: null, agent: 'ARCHITECT' },
    { keywords: ['migration', 'schema', 'banco de dados', 'database', 'tabela', 'pgvector', 'postgres'], skill: null, agent: 'DBA' },
    { keywords: ['bug', 'erro', 'fix', 'debug', 'quebrou', 'falhando'], skill: null, agent: 'DEBUG' },
    { keywords: ['teste', 'test', 'tdd', 'cobertura', 'coverage', 'pest', 'vitest', 'jasmine'], skill: null, agent: 'QA' },
    { keywords: ['commit', 'mensagem de commit', 'conventional commit'], skill: null, agent: 'GIT_COMMIT' },
    { keywords: ['documentar', 'docs', 'documentação', 'changelog'], skill: null, agent: 'DOC' },
    { keywords: ['review', 'revisar', 'code review'], skill: null, agent: 'REVIEWER' },
    { keywords: ['angular', 'componente', 'ui', 'interface', 'página', 'tela', 'ionic', 'capacitor'], skill: null, agent: 'FRONTEND' },
    { keywords: ['laravel', 'api', 'endpoint', 'controller', 'action', 'dto', 'sanctum', 'tenant'], skill: null, agent: 'BACKEND' },
    { keywords: ['nestjs', 'gateway', 'bullmq', 'redis stream', 'websocket', 'socket.io'], skill: null, agent: 'GATEWAY' },
    { keywords: ['electron', 'desktop', 'eletron'], skill: null, agent: 'FRONTEND' },
    { keywords: ['layout', 'design', 'mockup', 'wireframe', 'ux'], skill: null, agent: 'DESIGNER' },
    { keywords: ['whatsapp', 'uazapi', 'z-api', 'webhook', 'inbound', 'outbound'], skill: null, agent: 'GATEWAY' },
  ];

  for (const item of skillMap) {
    if (item.keywords.some(kw => message.includes(kw))) {
      return item;
    }
  }

  return null;
}

function determineComplexity(userMessage) {
  const message = userMessage.toLowerCase();

  const complexIndicators = [
    'módulo', 'múltiplas', 'multi', 'backend e frontend', 'end-to-end',
    'cross', 'refatorar', 'migrar', 'dashboard', 'relatório', 'integração',
    'feature completa', 'sistema', 'fluxo completo', 'tenant', 'rbac'
  ];
  const simpleIndicators = [
    'bug', 'fix', 'corrigir', 'erro', 'ajuste', 'pequeno', 'simples',
    'typo', 'rename', 'mover', 'deletar'
  ];

  const complexScore = complexIndicators.filter(i => message.includes(i)).length;
  const simpleScore = simpleIndicators.filter(i => message.includes(i)).length;

  return complexScore > simpleScore ? 'complex' : 'simple';
}

function loadAgentSummary() {
  const agentsDir = path.join(PROJECT_ROOT, '.claude', 'agents');
  let summary = '\n### Agents Disponíveis\n';
  summary += '| Agent | Fase PREVC | Trigger |\n';
  summary += '|-------|-----------|--------|\n';

  const agentPhases = {
    'PM': 'Planning, Confirm',
    'ARCHITECT': 'Planning, Review',
    'REVIEWER': 'Review',
    'BACKEND': 'Execution (Laravel 12 / PHP 8.3)',
    'FRONTEND': 'Execution (Angular 19 / Ionic / Electron)',
    'GATEWAY': 'Execution (NestJS 11 / TS 5.7)',
    'DEV': 'Execution (cross-camada)',
    'DBA': 'Execution (PostgreSQL 17 / pgvector)',
    'QA': 'Validation',
    'DEBUG': 'Execution (bugs)',
    'DOC': 'Confirm',
    'GIT_COMMIT': 'Confirm',
    'ORCHESTRATOR': 'Todas',
    'DESIGNER': 'Planning'
  };

  try {
    const files = fs.readdirSync(agentsDir);
    for (const file of files) {
      if (file.endsWith('.md')) {
        const name = file.replace('.md', '');
        const phase = agentPhases[name] || 'N/A';
        summary += `| @${name} | ${phase} | Ver .claude/agents/${file} |\n`;
      }
    }
  } catch {
    summary += '| (nenhum agent encontrado) | | |\n';
  }

  return summary;
}

function checkPREVCState() {
  const featuresDir = path.join(PROJECT_ROOT, '.context', 'DOCS', 'FEATURES');
  const tasksDir = path.join(PROJECT_ROOT, '.context', 'DOCS', 'TASKS');

  let state = '\n### Estado PREVC Atual\n';

  try {
    const features = fs.readdirSync(featuresDir).filter(f => f.endsWith('.md') && !f.startsWith('_') && f !== 'README.md');
    const tasks = fs.readdirSync(tasksDir).filter(f => f.endsWith('.md') && !f.startsWith('_') && f !== 'README.md');

    state += `- Features documentadas: ${features.length}\n`;
    state += `- Arquivos de tasks: ${tasks.length}\n`;

    if (features.length > 0) {
      state += `- Última feature: ${features[features.length - 1]}\n`;
    }
  } catch {
    state += '- Nenhuma feature/task encontrada ainda\n';
  }

  return state;
}

async function main() {
  try {
    const stdinData = await readStdin();
    const userMessage = extractUserMessage(stdinData);

    if (!userMessage || userMessage.trim().length === 0) {
      process.stdout.write(JSON.stringify({ continue: true }));
      return;
    }

    const match = findMatchingSkill(userMessage);
    const complexity = determineComplexity(userMessage);

    let additionalContext = '';

    if (match && match.skill) {
      additionalContext = `
## Roteamento Automático

**Comando sugerido:** /${match.skill}
**Agent primário:** @${match.agent}
**Complexidade:** ${complexity}

> Workflow PREVC: consulte \`.context/WORKFLOW/PREVC.md\`
> Sempre registre decisões em \`.context/DOCS/MEMORY/\`
> Ao concluir tasks, atualize \`.context/DOCS/CHANGELOG/\`
`;
    } else if (match && match.agent) {
      additionalContext = `
## Roteamento Automático

**Agent recomendado:** @${match.agent}
**Complexidade:** ${complexity}
**Consultar:** \`.claude/agents/${match.agent}.md\`

> Workflow PREVC: consulte \`.context/WORKFLOW/PREVC.md\`
> Sempre registre decisões em \`.context/DOCS/MEMORY/\`
`;
    } else if (complexity === 'complex') {
      additionalContext = `
## Roteamento Automático

**Nenhuma skill/agent específico identificado**
**Complexidade:** ALTA — recomendado @ORCHESTRATOR
**Consultar:** \`.claude/agents/ORCHESTRATOR.md\`

> Para tarefas complexas, siga o PREVC: \`.context/WORKFLOW/PREVC.md\`
`;
    } else {
      additionalContext = `
## Roteamento Automático

**Complexidade:** Simples
**Recomendação:** Resposta direta ou @DEV
`;
    }

    const agentSummary = loadAgentSummary();
    const prevcState = checkPREVCState();

    const output = {
      continue: true,
      hookSpecificOutput: {
        hookEventName: 'UserPromptSubmit',
        additionalContext: (additionalContext + agentSummary + prevcState).trim()
      }
    };

    process.stdout.write(JSON.stringify(output));

  } catch {
    process.stdout.write(JSON.stringify({ continue: true }));
  }
}

main();

#!/usr/bin/env node
/**
 * Router Hook — InteraZap
 *
 * Lê a mensagem do usuário via stdin, identifica o agent/skill adequado
 * e injeta contexto adicional para o Claude Code.
 *
 * Stack: Laravel 12 / NestJS 11 / Angular 20 / PostgreSQL
 * Workflow: PREVC (Planning → Review → Execution → Validation → Confirm)
 * Agents: .context/AGENTS/
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
    { keywords: ['nova feature', 'criar feature', 'new feature', 'planejar feature', 'documentar feature'], skill: 'new-feature', agent: 'PM' },
    { keywords: ['revisar feature', 'review feature', 'aprovar feature', 'validar feature doc'], skill: 'review-feature', agent: 'REVIEWER' },
    { keywords: ['decompor', 'decompose', 'quebrar em tasks', 'criar tasks', 'gerar tasks'], skill: 'decompose', agent: 'ARCHITECT' },
    { keywords: ['validar tasks', 'validate tasks', 'checar tasks'], skill: 'validate-tasks', agent: 'QA' },
    { keywords: ['implementar task', 'implement task', 'executar task'], skill: 'implement-task', agent: 'DEV' },
    { keywords: ['validar implementação', 'validate', 'rodar gates', 'gates', 'rodar testes'], skill: 'validate', agent: 'QA' },
    { keywords: ['confirmar task', 'confirm task', 'fechar task', 'concluir task'], skill: 'confirm-task', agent: 'DOC' },
    { keywords: ['status feature', 'feature status', 'progresso da feature'], skill: 'feature-status', agent: 'PM' },
    { keywords: ['review fase', 'revisar fase', 'review phase'], skill: 'review-phase', agent: 'REVIEWER' },
    { keywords: ['validate phase', 'validar fase'], skill: 'validate-phase', agent: 'QA' },
    { keywords: ['confirm phase', 'confirmar fase', 'fechar fase'], skill: 'confirm-phase', agent: 'DOC' },

    // Especialidades InteraZap
    { keywords: ['prd', 'product requirements', 'requisitos de produto'], skill: null, agent: 'PM' },
    { keywords: ['arquitetura', 'architecture', 'ddd', 'bounded context', 'diagrama de módulos'], skill: null, agent: 'ARCHITECT' },
    { keywords: ['migration', 'schema', 'banco de dados', 'database', 'tabela', 'foreign key', 'index'], skill: null, agent: 'DBA' },
    { keywords: ['bug', 'erro', 'fix', 'debug', 'quebrou', 'falhando', 'exception', 'null pointer'], skill: null, agent: 'DEBUG' },
    { keywords: ['teste', 'test', 'tdd', 'cobertura', 'coverage', 'pest', 'vitest', 'jest'], skill: null, agent: 'QA' },
    { keywords: ['commit', 'mensagem de commit', 'conventional commits'], skill: null, agent: 'GIT_COMMIT' },
    { keywords: ['documentar', 'docs', 'changelog', 'memory', 'adr'], skill: null, agent: 'DOC' },
    { keywords: ['code review', 'revisar código', 'pr review', 'pull request'], skill: null, agent: 'REVIEWER' },
    { keywords: ['angular', 'componente', 'frontend', 'ui', 'interface', 'página', 'tela', 'capacitor', 'ionic'], skill: null, agent: 'FRONTEND' },
    { keywords: ['laravel', 'backend', 'api', 'endpoint', 'service', 'action', 'job', 'event', 'queue', 'php'], skill: null, agent: 'BACKEND' },
    { keywords: ['nestjs', 'gateway', 'websocket', 'socket.io', 'whatsapp provider', 'uazapi', 'z-api'], skill: null, agent: 'GATEWAY' },
    { keywords: ['layout', 'design', 'mockup', 'wireframe', 'ux', 'figma', 'prototipo'], skill: null, agent: 'DESIGNER' },
    { keywords: ['plano', 'plan', 'escopo', 'abordagem técnica', 'planejar implementação'], skill: null, agent: 'PLAN' },
    { keywords: ['whatsapp', 'mensagem', 'chat', 'ticket', 'atendimento', 'automação'], skill: null, agent: 'BACKEND' },
    { keywords: ['billing', 'asaas', 'invoice', 'pagamento', 'cobrança', 'plano'], skill: null, agent: 'BACKEND' },
    { keywords: ['multi-tenant', 'tenant', 'isolamento', 'belongs to tenant'], skill: null, agent: 'ARCHITECT' },
    { keywords: ['ai autopilot', 'embedding', 'openai', 'rag', 'vector', 'ai tool'], skill: null, agent: 'BACKEND' },
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
    'módulo', 'múltiplas', 'multi', 'backend e frontend', 'end-to-end', 'cross',
    'refatorar', 'migrar', 'dashboard', 'relatório', 'integração', 'feature completa',
    'sistema', 'fluxo completo', 'cross-workspace', 'dba', 'gateway e', 'e gateway'
  ];
  const simpleIndicators = [
    'bug', 'fix', 'corrigir', 'erro', 'ajuste', 'pequeno', 'simples',
    'typo', 'rename', 'mover', 'deletar', 'atualizar texto'
  ];

  const complexScore = complexIndicators.filter(i => message.includes(i)).length;
  const simpleScore = simpleIndicators.filter(i => message.includes(i)).length;
  return complexScore > simpleScore ? 'complex' : 'simple';
}

function loadAgentSummary() {
  const agentsDir = path.join(PROJECT_ROOT, '.context', 'AGENTS');
  let summary = '\n### Agents Disponíveis\n';
  summary += '| Agent | Especialidade |\n';
  summary += '|-------|---------------|\n';

  const agentRoles = {
    'ORCHESTRATOR': 'Coordenação multi-workspace',
    'PM': 'Feature docs, escopo, Planning/Confirm',
    'PLAN': 'Planejar abordagem técnica',
    'ARCHITECT': 'DDD, decisões de arquitetura',
    'REVIEWER': 'Code review, doc review',
    'BACKEND': 'Laravel 12 / PHP — Execution',
    'GATEWAY': 'NestJS 11 / Socket.io — Execution',
    'FRONTEND': 'Angular 20 / Capacitor — Execution',
    'DBA': 'PostgreSQL, migrations — Execution',
    'DEV': 'Cross-workspace — Execution',
    'QA': 'Gates, coverage, multi-tenant isolation',
    'DEBUG': 'Bug investigation',
    'DOC': 'CHANGELOG, MEMORY, documentação',
    'GIT_COMMIT': 'Commits semânticos pt-BR',
    'DESIGNER': 'UI/UX, wireframes',
    'VIBE-CODER': 'Tarefas criativas/exploratórias',
  };

  try {
    const files = fs.readdirSync(agentsDir);
    for (const file of files) {
      if (file.endsWith('.md')) {
        const name = file.replace('.md', '');
        const role = agentRoles[name] || 'N/A';
        summary += `| @${name} | ${role} |\n`;
      }
    }
  } catch {
    summary += '| (agents em .context/AGENTS/) | |\n';
  }

  return summary;
}

function checkPREVCState() {
  const featuresDir = path.join(PROJECT_ROOT, '.context', 'DOCS', 'FEATURES');
  const tasksDir = path.join(PROJECT_ROOT, '.context', 'DOCS', 'TASKS');

  let state = '\n### Estado PREVC Atual\n';

  try {
    const features = fs.readdirSync(featuresDir).filter(f => f.endsWith('.md') && !f.startsWith('_') && f !== 'README.md');
    state += `- Features documentadas: ${features.length}\n`;
    if (features.length > 0) {
      state += `- Última feature: ${features[features.length - 1]}\n`;
    }
  } catch {
    state += '- Nenhuma feature ainda\n';
  }

  try {
    const tasks = fs.readdirSync(tasksDir).filter(f => f.endsWith('.md') && !f.startsWith('_') && f !== 'README.md');
    state += `- Arquivos de tasks: ${tasks.length}\n`;
  } catch {
    state += '- Nenhum arquivo de tasks ainda\n';
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

> Workflow PREVC: \`.context/WORKFLOW/PREVC.md\`
> Consultar MEMORY antes de decidir: \`.context/DOCS/MEMORY/\`
> Ao confirmar tasks: atualizar \`.context/DOCS/CHANGELOG/\`
`;
    } else if (match && match.agent) {
      additionalContext = `
## Roteamento Automático

**Agent recomendado:** @${match.agent}
**Complexidade:** ${complexity}
**Consultar:** \`.context/AGENTS/${match.agent}.md\`

> Workflow PREVC: \`.context/WORKFLOW/PREVC.md\`
> Consultar MEMORY antes de decidir: \`.context/DOCS/MEMORY/\`
`;
    } else if (complexity === 'complex') {
      additionalContext = `
## Roteamento Automático

**Nenhum agent específico identificado**
**Complexidade:** ALTA — recomendado @ORCHESTRATOR
**Consultar:** \`.context/AGENTS/ORCHESTRATOR.md\`

> Para tarefas complexas cross-workspace, seguir PREVC: \`.context/WORKFLOW/PREVC.md\`
> Ordem padrão: DBA → BACKEND → GATEWAY → FRONTEND
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

# Detect Project

> Skill para detectar e documentar o contexto do projeto.

---

## Quando Usar

1. Ao iniciar um projeto novo
2. Ao integrar com projeto existente
3. Ao fazer manutenção e precisar entender contexto

---

## O que Detectar

### 1. Stack Tecnológica

| Camada | O que buscar | Fontes |
|--------|-------------|--------|
| Backend | Linguagem + Framework | `composer.json`, `package.json`, `requirements.txt` |
| Frontend | Framework + Versão | `package.json`, `angular.json`, `tsconfig.json` |
| Database | Tipo + Versão | `.env`, `docker-compose`, `config/database.php` |
| Cache/Queue | Tecnologia | Redis, RabbitMQ, SQS |

### 2. Arquitetura

**Identificar padrão:**
- DDD (Domain-Driven Design)
- MVC Tradicional
- Clean Architecture
- Hexagonal
- Microserviços

**Identificar camadas:**
```
Domain (Entities, Services, Events)
         ↓
Application (Use Cases, Commands, Queries)
         ↓
Infrastructure (DB, External Services)
         ↓
Presentation (Controllers, Views)
```

### 3. Módulos/Bounded Contexts

Buscar por:
- Pastas em `src/` ou `app/`
- namespaces PHP (PSR-4 autoload)
- modules/ ou bounded-contexts/
- Contexto em nomes de arquivos

### 4. Convenções

| Tipo | O que buscar | Exemplo |
|------|-------------|---------|
| Nomenclatura de arquivos | Extensões + case | `kebab-case.ts`, `PascalCase.php` |
| Nomenclatura de classes | Case usado | `PascalCase`, `camelCase` |
| Git flow | Formato de branch | `feature/FEAT-NNN`, `fix/` |
| Commit format | Conventional Commits? | `type(scope): description` |

### 5. Business Rules

Buscar por:
- Multi-tenancy
- Regras de validação
- Fluxos de aprovação
- Integrações externas

---

## Comandos de Detecção

### Detectar Stack

```bash
# Backend
cat composer.json | grep -E "laravel|php"
cat requirements.txt | grep -E "django|flask|fastapi"

# Frontend
cat package.json | grep -E "angular|react|vue|next"

# Database
grep -r "DB_" .env.example
grep -E "postgres|mysql|mongo" docker-compose*
```

### Detectar Arquitetura

```bash
# DDD folders
find . -type d -name "Domain" -o -name "Entities" -o -name "Services"

# Layered architecture
find . -type d -name "Controllers" -o -name "Models" -o -name "Views"

# Modules
find . -type d -name "modules" -o -name "bounded-contexts"
```

### Detectar Testes

```bash
# Test framework
grep -r "jest\|vitest\|pest\|phpunit\|pytest" package.json composer.json

# Test files
find . -name "*.spec.ts" -o -name "*Test.php" -o -name "test_*.py"
```

---

## Output: Context Document

Após detectar, documente em `.context/ARCHITECTURE/project-brain.yaml`:

```yaml
project: "[Nome]"
stack:
  backend:
    language: "[PHP/Python/JS]"
    framework: "[Laravel/Django/Express]"
  frontend:
    language: "[TS/JS]"
    framework: "[Angular/React/Vue]"
  database:
    type: "[PostgreSQL/MySQL]"
architecture:
  pattern: "[DDD/MVC/Clean]"
  layers: [...]
modules:
  - name: "[Module1]"
  - name: "[Module2]"
conventions:
  file_naming: "[kebab-case/PascalCase]"
  class_naming: "[PascalCase]"
```

---

## Quando Não Detectar

Se `.context/ARCHITECTURE/project-brain.yaml` já existe:
1. Leia o arquivo existente
2. Não sobrescreva
3. Apenas atualize se houver mudanças significativas

---

## Armadilhas

1. **Não assumir** — Sempre verificar, não assumir "é igual ao anterior"
2. **Verificar versões** — "Laravel" não é suficiente, specify "Laravel 12"
3. **Contexto importa** — A mesma stack pode ter convenções diferentes por projeto

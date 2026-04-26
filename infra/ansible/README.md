# InteraZap - Ansible Deployment

## Estrutura do Projeto

```
infra/ansible/
├── ansible.cfg                    # Configuração do Ansible
├── playbook.yml                  # Playbook principal (production + staging)
├── inventory/
│   └── hosts.ini                # Inventário de hosts
├── vars/
│   └── vault.yml.example        # Template de variáveis seguras
└── roles/
    ├── common/                   # Configuração base (timezone, locale)
    ├── users/                    # Usuário deploy + SSH
    ├── security/                 # UFW, Fail2Ban, SSH hardening
    ├── nginx/                    # Proxy reverso (8 sites)
    ├── ssl/                      # Certbot SSL
    ├── php/                      # PHP 8.5.3 + Swoole + Octane
    ├── redis/                    # Redis com autenticação
    ├── postgres/                 # PostgreSQL 18 + pgvector
    ├── supervisor/               # Workers PHP (Octane + Horizon + Consumers)
    ├── nodejs/                   # Node.js 20 LTS + PM2
    ├── app-deploy/               # Deploy API + Gateway + App
    └── landing-deploy/           # Landing pages
```

---

## Pré-requisitos

### 1. Instalar Ansible

```bash
# macOS
brew install ansible

# Ubuntu/Debian
sudo apt update
sudo apt install ansible

# Verify
ansible --version
```

### 2. Gerar chave SSH do Ansible (para conectar ao servidor)

```bash
ssh-keygen -t ed25519 -C "ansible@interazap" -f ~/.ssh/ansible
```

### 3. Configurar Vault

```bash
cd infra/ansible

# Copiar template
cp vars/vault.yml.example vars/vault.yml

# Editar com valores reais
ansible-vault edit vars/vault.yml
```

**Variáveis obrigatórias no vault:**

```yaml
agentflix_password: 'SENHA_STRONG'
postgres_root_password: 'POSTGRES_ROOT_SENHA'
postgres_app_password: 'POSTGRES_APP_SENHA'
redis_password: 'REDIS_SENHA'
agentflix_secret_key: 'LARAVEL_APP_KEY'
gateway_internal_api_key: 'UUID-GERADO'
gateway_jwt_secret: 'JWT_32_CHARS_MINIMO'
openai_api_key: 'sk-...'
ssh_port: 22
```

---

## Deploy - Passo a Passo

> O playbook tem **dois plays separados**:
> - **Play 1 — `provision`**: roda como `root`, cria o usuário deploy, instala pacotes base, aplica hardening. Rodar **uma única vez** em VPS nova.
> - **Play 2 — `setup,deploy`**: roda como `deploy`, instala nginx/PHP/PostgreSQL/etc e faz deploy da aplicação. Executado automaticamente pelo GitHub Actions a cada push.

### PASSO 1: Atualizar known_hosts (VPS nova ou após reset)

```bash
ssh-keygen -R 186.202.209.180
ssh-keyscan -H 186.202.209.180 >> ~/.ssh/known_hosts
```

### PASSO 2: Provisionamento inicial (Play 1 — como root, somente uma vez)

```bash
cd infra/ansible
ansible-playbook playbook.yml -e ansible_user=root --ask-vault-pass --limit production --tags provision
```

Aguarde `ok=55, failed=0`. Depois adicione a chave pública gerada (`/home/deploy/.ssh/id_ed25519.pub`) como **Deploy Key** no GitHub.

### PASSO 3: Setup + Deploy (Play 2 — via GitHub Actions)

Após o provisionamento, o GitHub Actions cuida do restante automaticamente:
- `push → main` → deploy em **production**
- `push → develop` → deploy em **staging**

Para disparar manualmente: **GitHub → Actions → Deploy Production → Run workflow**

### PASSO 4 (opcional): Rodar Play 2 manualmente

```bash
cd infra/ansible
ansible-playbook playbook.yml --ask-vault-pass --limit production --tags setup,deploy
```

**O Ansible vai:**

1. Criar usuário `deploy` com sudo
2. Gerar chave SSH em `/home/deploy/.ssh/id_ed25519.pub`
3. Instalar todos os serviços
4. Configurar SSL

### PASSO 3: Copiar a chave SSH pública do deploy

Após o Ansible rodar, a chave pública estará em:

- `/root/deploy_ssh_public_key.txt` (copiado pelo Ansible)
- `/home/deploy/.ssh/id_ed25519.pub`

```bash
# No servidor, ver a chave
cat /home/deploy/.ssh/id_ed25519.pub
```

### PASSO 4: Configurar GitHub Secrets

No GitHub, vá em **Settings → Secrets and variables → Actions** e adicione:

| Secret               | Valor                                                          |
| -------------------- | -------------------------------------------------------------- |
| `VPS_HOST`           | `186.202.209.180`                                              |
| `VPS_SSH_PORT`       | `22`                                                           |
| `VPS_DEPLOY_USER`    | `deploy`                                                       |
| `VPS_DEPLOY_SSH_KEY` | Cole a **chave privada** (`~/.ssh/deploy` ou `~/.ssh/ansible`) |

### PASSO 5: Adicionar Deploy Key no GitHub

1. Vá em **Settings → Deploy Keys** do repositório
2. Adicione a chave pública (`/home/deploy/.ssh/id_ed25519.pub`)
3. Marque "Allow write access"

### PASSO 6: Configurar known_hosts

```bash
# No seu computador, adicionar o servidor ao known_hosts
ssh-keyscan -H 186.202.209.180 >> ~/.ssh/known_hosts
```

### PASSO 7: Testar o deploy via GitHub Actions

```bash
# Fazer push para develop (staging) ou main (production)
git push origin develop  # Trigger staging
git push origin main     # Trigger production
```

---

## Comandos Úteis

### Executar roles específicas

```bash
# Apenas nginx + ssl
ansible-playbook -i inventory/hosts.ini playbook.yml --tags nginx,ssl --ask-vault-pass

# Apenas workers (supervisor)
ansible-playbook -i inventory/hosts.ini playbook.yml --tags supervisor --ask-vault-pass

# Apenas deploy
ansible-playbook -i inventory/hosts.ini playbook.yml --tags deploy --ask-vault-pass
```

### Verificar serviços no servidor

```bash
# SSH como deploy
ssh -i ~/.ssh/ansible deploy@186.202.209.180

# Ver status dos serviços
sudo supervisorctl status
pm2 status
systemctl status nginx
systemctl status postgresql
systemctl status redis-server

# Ver logs
sudo tail -f /var/log/supervisor/interazap-octane.log
pm2 logs interazap-gateway-prod
```

### Reiniciar serviços manualmente

```bash
# Todos os workers PHP
sudo supervisorctl restart interazap:*

# Gateway
pm2 restart interazap-gateway-prod

# Nginx
sudo systemctl reload nginx
```

### Rollback rápido

```bash
# Se algo der errado, voltar código
sudo mv /data/production/api /data/production/apiBroken
sudo mv /data/production/api.bak /data/production/api
sudo supervisorctl restart interazap:*
```

---

## Estrutura de Pastas no Servidor

```
/data/
├── production/
│   ├── api/                    # Laravel API
│   │   └── api/              # (artisan está aqui)
│   │       ├── .env
│   │       ├── app/
│   │       ├── bootstrap/
│   │       ├── config/
│   │       ├── database/
│   │       ├── public/
│   │       ├── resources/
│   │       ├── routes/
│   │       ├── storage/
│   │       └── vendor/
│   ├── app/                    # Angular build
│   │   └── dist/interazap/browser/
│   ├── gateway/               # NestJS build
│   │   └── dist/
│   └── landing/              # HTML estático
│       └── public/
│
└── stage/
    └── (mesma estrutura)
```

## Portas e Domínios

| Domínio                          | Serviço          | Porta  | SSL |
| -------------------------------- | ---------------- | ------ | --- |
| `www.interazap.com.br`           | Landing          | 80/443 | ✅  |
| `app.interazap.com.br`           | Angular          | 443    | ✅  |
| `api.interazap.com.br`           | Laravel (Octane) | 8082   | -   |
| `gateway.interazap.com.br`       | NestJS           | 6002   | ✅  |
| `stage.www.interazap.com.br`     | Landing          | 8080   | -   |
| `stage.app.interazap.com.br`     | Angular          | 4200   | -   |
| `stage.api.interazap.com.br`     | Laravel (Octane) | 8081   | -   |
| `stage.gateway.interazap.com.br` | NestJS           | 6001   | -   |

---

## Workers PHP (Supervisor)

| Worker                       | Comando                         | Ambiente   |
| ---------------------------- | ------------------------------- | ---------- |
| `interazap-octane`           | `octane:start --workers=swoole` | Production |
| `interazap-streams-chat`     | `streams:chat-consume`          | Production |
| `interazap-ai-run-responses` | `ai:consume-run-responses`      | Production |
| `interazap-ai-tool-requests` | `ai:consume-tool-requests`      | Production |
| `interazap-horizon`          | `horizon`                       | Production |

## Workers Node.js (PM2)

| App                       | Porta | Ambiente   |
| ------------------------- | ----- | ---------- |
| `interazap-gateway-prod`  | 6002  | Production |
| `interazap-gateway-stage` | 6001  | Staging    |

---

## Troubleshooting

### SSH connection refused

```bash
# Verificar se a porta está correta
ssh -i ~/.ssh/ansible -p 22 deploy@186.202.209.180

# Testar com verbose
ssh -vvv -i ~/.ssh/ansible deploy@186.202.209.180
```

### Ansible fails on first run

```bash
# Verificar se o vault está correto
ansible-vault view vars/vault.yml

# Re-criar vault
ansible-vault create vars/vault.yml
```

### Services not starting

```bash
# Ver logs do supervisor
sudo tail -100 /var/log/supervisor/interazap-octane.log

# Verificar se as portas estão em uso
sudo netstat -tlnp | grep -E '8082|9501|6002'
```

### Database connection fails

```bash
# Testar conexão PostgreSQL
psql -h 127.0.0.1 -U agentflix_app -d interazap

# Ver logs PostgreSQL
sudo tail -50 /var/log/postgresql/postgresql-18-main.log
```

---

## Segurança

- [x] UFW firewall (portas 22, 80, 443, 8080, 8081, 4200, 6001, 6002, 8082)
- [x] Fail2Ban (SSH + Nginx)
- [x] SSH hardening (sem root, sem password)
- [x] Redis com senha obrigatória
- [x] PostgreSQL SCRAM-SHA-256
- [x] Nginx headers de segurança
- [x] Auto-renew SSL com Certbot
- [x] Updates automáticos de segurança

---

## Próximos Passos (não incluídos)

- [ ] Script de backup (PostgreSQL dumps)
- [ ] Monitoramento (Prometheus + Grafana)
- [ ] Alertas de disco/memória
- [ ] Log aggregation
- [ ] CI/CD mais robusto (testes, lint)

---

## Versões

| Componente | Versão    |
| ---------- | --------- |
| Ubuntu     | 24.04 LTS |
| PHP        | 8.5.3     |
| PostgreSQL | 18        |
| Redis      | 7         |
| Node.js    | 20 LTS    |
| Nginx      | latest    |

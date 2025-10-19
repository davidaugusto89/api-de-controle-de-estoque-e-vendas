# API de Controle de Estoque e Vendas

Este repositório contém uma API para controle de estoque e vendas desenvolvida em Laravel.

## Objetivo

Fornecer uma API RESTful para gerenciar produtos, inventário e vendas, com recursos para relatórios e integração.

## Requisitos

- PHP 8.1+ (compatível com a versão usada no projeto)
- Composer
- Docker & Docker Compose (recomendado)
- MySQL (ou via Docker)

## Como rodar (com Docker)

1. Copie o arquivo de ambiente e ajuste as variáveis se necessário:

```bash
cp backend/.env.example backend/.env
```

2. Subir os serviços com Docker Compose:

```bash
docker-compose up -d --build
```

3. Executar migrations e seeders (dentro do container `backend`):

```bash
docker compose exec backend php artisan migrate --seed
```

4. A API ficará exposta conforme configuração do `docker-compose.yml` (verifique a porta configurada).

## Como rodar localmente (sem Docker)

1. Instale dependências:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

2. Configure o banco no `.env` e rode migrations:

```bash
php artisan migrate --seed
php artisan serve
```

## Testes

Executar testes PHPUnit:

```bash
cd backend
./vendor/bin/phpunit
```

## Fila & Scheduler

Instruções rápidas para trabalhar com filas e scheduler.

Local (sem Docker):

```bash
# Inicia um worker para processar filas (use a conexão definida em QUEUE_CONNECTION)
cd backend
php artisan queue:work --tries=3 --sleep=3 --queue=default,inventory,sales

# Para rodar o agendador manualmente (útil para desenvolvimento)
php artisan schedule:run
```

Com Docker Compose (container `backend` já configurado):

```bash
# Execute um worker dentro do container
docker compose exec backend php artisan queue:work --tries=3 --sleep=3 --queue=default,inventory,sales

# Rodar o scheduler uma vez (cronagem real deve executar `php artisan schedule:run` a cada minuto)
docker compose exec backend php artisan schedule:run
```

Observações:

- O projeto usa Redis como driver de fila/cache por padrão (ver `backend/.env.example`).
- Use Horizon (se configurado) para observar filas em produção/local.
- Certifique-se de executar `php artisan migrate --seed` antes de processar filas que dependam de dados.

## Estrutura principal

- `app/` - Código da aplicação (Domain, Application, Infrastructure)
- `routes/` - Rotas da aplicação
- `database/` - Migrations, factories e seeders
- `tests/` - Testes automatizados

## Contribuição

Regras básicas:

- Abra issues para bugs e features
- Use branches por feature/bugfix
- Prefira PRs pequenas e com descrição clara

## Contato

Autor: David

---

Arquivo gerado automaticamente pelo assistente para iniciar o projeto.

